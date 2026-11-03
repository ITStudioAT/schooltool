<?php

use App\Ai\Agents\MultipleChoiceDraftAgent;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\CloudwaysSchoolSynchronizationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Storage\DatabaseConversationStore;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (config('ai.conversations.connection') === 'ai_upgrade_fixture') {
        $schema = DB::connection('ai_upgrade_fixture')->getSchemaBuilder();
        $schema->dropIfExists('ai_upgrade_messages_fixture');
        $schema->dropIfExists('ai_upgrade_conversations_fixture');
        DB::purge('ai_upgrade_fixture');
    }
});

function aiUpgradeMigration(): object
{
    return require database_path('migrations/2026_10_21_235344_upgrade_agent_conversations_for_ai_v1.php');
}

function prepareLegacyAiSchemaForUpgradeTest(): void
{
    config([
        'database.connections.ai_upgrade_fixture' => config('database.connections.'.config('database.default')),
        'ai.conversations.connection' => 'ai_upgrade_fixture',
        'ai.conversations.tables.conversations' => 'ai_upgrade_conversations_fixture',
        'ai.conversations.tables.messages' => 'ai_upgrade_messages_fixture',
    ]);
    $schema = DB::connection('ai_upgrade_fixture')->getSchemaBuilder();
    $schema->create('ai_upgrade_conversations_fixture', function (Blueprint $table): void {
        $table->string('id', 36)->primary();
        $table->foreignId('user_id')->nullable();
        $table->string('title');
        $table->timestamps();
        $table->index(['user_id', 'updated_at']);
    });
    $schema->create('ai_upgrade_messages_fixture', function (Blueprint $table): void {
        $table->string('id', 36)->primary();
        $table->string('conversation_id', 36)->index();
        $table->foreignId('user_id')->nullable();
        $table->string('agent');
        $table->string('role', 25);
        foreach (['content', 'attachments', 'tool_calls', 'tool_results', 'usage', 'meta'] as $column) {
            $table->text($column);
        }
        $table->timestamps();
        $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
        $table->index(['user_id']);
    });
}

test('AI upgrade preserves legacy history and replays tool results without rewriting original data', function (): void {
    prepareLegacyAiSchemaForUpgradeTest();
    $fixture = DB::connection('ai_upgrade_fixture');
    $user = User::factory()->create();
    $fixture->table('ai_upgrade_conversations_fixture')->insert([
        'id' => 'legacy-conversation', 'user_id' => $user->id, 'title' => 'History',
    ]);
    $legacy = [
        'id' => 'legacy-message', 'conversation_id' => 'legacy-conversation', 'user_id' => $user->id,
        'agent' => MultipleChoiceDraftAgent::class, 'role' => 'assistant', 'content' => 'Historische Antwort',
        'attachments' => '[]',
        'tool_calls' => '[{"id":"call_1","name":"lookup","arguments":{"topic":"test"}}]',
        'tool_results' => '[{"id":"call_1","name":"lookup","arguments":{"topic":"test"},"result":"Beleg"}]',
        'usage' => '{"prompt_tokens":7,"completion_tokens":3}',
        'meta' => '{"reasoning":"Begründung","provider":"openai"}',
    ];
    $fixture->table('ai_upgrade_messages_fixture')->insert($legacy);

    $migration = aiUpgradeMigration();
    $migration->up();
    $migration->up();
    $migration->down();

    $stored = (array) $fixture->table('ai_upgrade_messages_fixture')->first();
    foreach ($legacy as $column => $value) {
        expect($stored[$column])->toBe($value);
    }
    expect($stored['participant_type'])->toBe($user->getMorphClass())
        ->and((int) $stored['participant_id'])->toBe($user->id)
        ->and($stored['status'])->toBe('completed');
    $steps = json_decode($stored['steps'], true, flags: JSON_THROW_ON_ERROR);
    expect($steps[0]['tool_calls'][0]['result'])->toBe('Beleg')
        ->and($steps[1]['content'])->toBe('Historische Antwort')
        ->and($steps[1]['reasoning'])->toBe('Begründung');
    $history = (new DatabaseConversationStore('ai_upgrade_fixture'))->getLatestConversationMessages('legacy-conversation', 10);
    expect($history)->toHaveCount(3)
        ->and($history[1])->toBeInstanceOf(ToolResultMessage::class)
        ->and($history[1]->toolResults->first()->result)->toBe('Beleg')
        ->and($history[2]->content)->toBe('Historische Antwort');
});

test('AI upgrade rejects malformed legacy JSON before changing the schema', function (): void {
    prepareLegacyAiSchemaForUpgradeTest();
    $fixture = DB::connection('ai_upgrade_fixture');
    $fixture->table('ai_upgrade_messages_fixture')->insert([
        'id' => 'bad-message', 'conversation_id' => 'bad-conversation', 'agent' => MultipleChoiceDraftAgent::class,
        'role' => 'assistant', 'content' => 'Erhalten', 'attachments' => '[]',
        'tool_calls' => 'broken json', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]',
    ]);

    expect(fn () => aiUpgradeMigration()->up())->toThrow(JsonException::class)
        ->and($fixture->getSchemaBuilder()->hasColumn('ai_upgrade_messages_fixture', 'steps'))->toBeFalse()
        ->and($fixture->table('ai_upgrade_messages_fixture')->value('content'))->toBe('Erhalten');
});

test('AI SDK writes new participants and messages while legacy columns remain available', function (): void {
    $user = User::factory()->create();
    $store = new DatabaseConversationStore;
    $conversation = $store->storeConversation($user->getMorphClass(), $user->id, 'New conversation');
    $message = $store->storeUserMessage($conversation, $user->getMorphClass(), $user->id, MultipleChoiceDraftAgent::class, new UserMessage('Neue Frage'));

    MultipleChoiceDraftAgent::fake([['questions' => [], 'warnings' => []]])->preventStrayPrompts();
    $response = (new MultipleChoiceDraftAgent)->prompt('Neue Frage', provider: 'openai');
    $capturedPrompt = null;
    MultipleChoiceDraftAgent::assertPrompted(function (AgentPrompt $prompt) use (&$capturedPrompt): bool {
        $capturedPrompt = $prompt;

        return true;
    });
    $assistantMessage = $store->storeAssistantMessage($conversation, $user->getMorphClass(), $user->id, $capturedPrompt, $response);

    expect($store->conversationBelongsTo($conversation, $user->getMorphClass(), $user->id))->toBeTrue()
        ->and($store->latestConversationId($user->getMorphClass(), $user->id, MultipleChoiceDraftAgent::class))->toBe($conversation)
        ->and($store->getLatestConversationMessages($conversation, 10)->first()->content)->toBe('Neue Frage')
        ->and(DB::table('agent_conversation_messages')->where('id', $message)->value('steps'))->toBe('[]')
        ->and(DB::table('agent_conversation_messages')->where('id', $assistantMessage)->value('status'))->toBe('completed')
        ->and(DB::table('agent_conversation_messages')->where('id', $assistantMessage)->value('tool_calls'))->toBeNull()
        ->and(Schema::hasColumns('agent_conversation_messages', ['user_id', 'tool_calls', 'tool_results']))->toBeTrue();
});

test('school synchronization includes user conversations and excludes other participant types and schools', function (): void {
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    $otherSchool = School::factory()->create();
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $otherUser = User::factory()->create(['school_id' => $otherSchool->id, 'schoolyear_id' => $otherYear->id]);
    $store = new DatabaseConversationStore;
    $owned = $store->storeConversation($user->getMorphClass(), $user->id, 'Owned');
    $ownedMessage = $store->storeUserMessage($owned, $user->getMorphClass(), $user->id, MultipleChoiceDraftAgent::class, new UserMessage('Frage'));
    $foreignType = $store->storeConversation(School::class, $user->id, 'Other participant');
    DB::table('agent_conversations')->where('id', $foreignType)->update(['user_id' => $user->id]);
    $store->storeConversation($otherUser->getMorphClass(), $otherUser->id, 'Other school');
    DB::table('agent_conversations')->insert(['id' => 'legacy', 'user_id' => $user->id, 'title' => 'Legacy']);

    $capture = new ReflectionMethod(CloudwaysSchoolSynchronizationService::class, 'captureGraph');
    $graph = $capture->invoke(app(CloudwaysSchoolSynchronizationService::class), DB::connection(), $school->id);

    expect(array_column($graph['rows']['agent_conversations'], 'id'))->toEqualCanonicalizing([$owned, 'legacy'])
        ->and(array_column($graph['rows']['agent_conversation_messages'], 'id'))->toBe([$ownedMessage]);
});
