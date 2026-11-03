<?php

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $conversations = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messages = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        $this->query($messages)->where('role', 'assistant')->orderBy('id')->chunkById(200, function (Collection $rows): void {
            foreach ($rows as $row) {
                foreach (['tool_calls', 'tool_results', 'meta'] as $column) {
                    $this->decoded($row->{$column});
                }
            }
        });

        foreach ([$conversations, $messages] as $name) {
            if (! $schema->hasColumn($name, 'participant_type')) {
                $schema->table($name, function (Blueprint $table): void {
                    $table->string('participant_type')->nullable();
                    $table->unsignedBigInteger('participant_id')->nullable();
                });
            }

            $this->query($name)->whereNull('participant_type')->whereNull('participant_id')
                ->whereNotNull('user_id')->update([
                    'participant_type' => (new User)->getMorphClass(),
                    'participant_id' => DB::raw('user_id'),
                ]);
        }

        if (! $schema->hasColumn($messages, 'steps')) {
            $schema->table($messages, function (Blueprint $table): void {
                $table->longText('steps')->nullable();
                $table->string('status', 25)->default('completed');
            });
        }

        $schema->table($messages, function (Blueprint $table): void {
            $table->text('tool_calls')->nullable()->change();
            $table->text('tool_results')->nullable()->change();
        });

        $this->query($messages)->whereNull('steps')->where('role', 'user')->update(['steps' => '[]']);
        $this->query($messages)->select('conversation_id')->distinct()->orderBy('conversation_id')
            ->chunk(100, function (Collection $conversations) use ($messages): void {
                foreach ($conversations as $conversation) {
                    $rows = $this->query($messages)->where('conversation_id', $conversation->conversation_id)
                        ->where('role', 'assistant')->orderBy('id')->get();
                    $results = $rows->flatMap(fn (object $row): array => $this->decoded($row->tool_results))->keyBy('id');

                    foreach ($rows as $row) {
                        if ($row->steps !== null) {
                            continue;
                        }

                        $calls = array_map(function (array $call) use ($results): array {
                            $result = $results->get($call['id'] ?? '');

                            return $result === null ? $call : [...$call, ...array_intersect_key($result, array_flip(['result', 'denied', 'failed']))];
                        }, $this->decoded($row->tool_calls));
                        $meta = $this->decoded($row->meta);
                        $steps = $calls !== [] && $row->content !== ''
                            ? [$this->step('', $calls), $this->step($row->content, [], $meta['reasoning'] ?? '')]
                            : [$this->step($row->content, $calls, $meta['reasoning'] ?? '')];

                        $this->query($messages)->where('id', $row->id)->update([
                            'steps' => json_encode($steps, JSON_THROW_ON_ERROR),
                        ]);
                    }
                }
            });

        if (! $schema->hasIndex($conversations, 'participant_updated_at_index')) {
            $schema->table($conversations, fn (Blueprint $table) => $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index'));
        }
        if (! $schema->hasIndex($messages, 'participant_index')) {
            $schema->table($messages, fn (Blueprint $table) => $table->index(['participant_type', 'participant_id', 'agent'], 'participant_index'));
        }
        if (! $schema->hasIndex($messages, 'ai_conversation_participant_index')) {
            $schema->table($messages, fn (Blueprint $table) => $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'ai_conversation_participant_index'));
        }
    }

    public function down(): void
    {
        // Retain both storage formats so a code rollback does not delete conversation data.
    }

    /** @return array<mixed> */
    private function decoded(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('AI conversation data must contain a JSON array or object.');
        }

        return $decoded;
    }

    /**
     * @param  list<array<string, mixed>>  $calls
     * @return array<string, mixed>
     */
    private function step(string $content, array $calls = [], string $reasoning = ''): array
    {
        return [
            'content' => $content, 'tool_calls' => $calls, 'reasoning' => $reasoning,
            'replay_blocks' => [], 'provider_tool_calls' => [],
        ];
    }

    private function query(string $table): Builder
    {
        return DB::connection($this->getConnection())->table($table);
    }
};
