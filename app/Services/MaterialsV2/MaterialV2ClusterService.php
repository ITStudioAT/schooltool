<?php

namespace App\Services\MaterialsV2;

use App\Models\MaterialV2Cluster;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaterialV2ClusterService
{
    /**
     * @return array<int, array{id:int,name:string,items_count:int}>
     */
    public function clusterDetails(User $user): array
    {
        return MaterialV2Cluster::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->withCount([
                'items' => fn (Builder $query): Builder => $query
                    ->whereBelongsTo($user)
                    ->where('school_id', $user->school_id),
            ])
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn (MaterialV2Cluster $cluster): array => [
                'id' => (int) $cluster->id,
                'name' => $cluster->name,
                'items_count' => (int) $cluster->items_count,
            ])
            ->all();
    }

    /**
     * @return array{
     *     name:?string,
     *     cluster:?MaterialV2Cluster,
     *     suggestion:?MaterialV2Cluster
     * }
     */
    public function resolve(
        User $user,
        ?string $name,
        bool $forceNewCluster = false,
    ): array {
        $clusterName = Str::squish((string) $name);
        if ($clusterName === '') {
            return [
                'name' => null,
                'cluster' => null,
                'suggestion' => null,
            ];
        }

        $normalizedName = $this->normalize($clusterName);
        if ($normalizedName === '') {
            throw ValidationException::withMessages([
                'cluster_name' => 'Bitte gib einen gültigen Namen für den Cluster ein.',
            ]);
        }

        $clusters = MaterialV2Cluster::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->orderBy('name')
            ->limit(500)
            ->get();
        $existingCluster = $clusters->first(
            fn (MaterialV2Cluster $cluster): bool => $cluster->normalized_name === $normalizedName,
        );

        if ($existingCluster instanceof MaterialV2Cluster) {
            return [
                'name' => $existingCluster->name,
                'cluster' => $existingCluster,
                'suggestion' => null,
            ];
        }

        return [
            'name' => $clusterName,
            'cluster' => null,
            'suggestion' => $forceNewCluster
                ? null
                : $this->closestCluster($clusters->all(), $normalizedName),
        ];
    }

    /**
     * @param  array{
     *     name:?string,
     *     cluster:?MaterialV2Cluster,
     *     suggestion:?MaterialV2Cluster
     * }  $resolution
     */
    public function persist(User $user, array $resolution): ?MaterialV2Cluster
    {
        if ($resolution['cluster'] instanceof MaterialV2Cluster) {
            return $resolution['cluster'];
        }

        if ($resolution['name'] === null) {
            return null;
        }

        return MaterialV2Cluster::query()->firstOrCreate(
            [
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'normalized_name' => $this->normalize($resolution['name']),
            ],
            ['name' => $resolution['name']],
        );
    }

    /**
     * @param  array<int, MaterialV2Cluster>  $clusters
     */
    private function closestCluster(array $clusters, string $normalizedName): ?MaterialV2Cluster
    {
        if (Str::length($normalizedName) < 3) {
            return null;
        }

        $closestCluster = null;
        $closestDistance = PHP_INT_MAX;

        foreach ($clusters as $cluster) {
            $normalizedExistingName = $cluster->normalized_name;
            $longestLength = max(
                Str::length($normalizedName),
                Str::length($normalizedExistingName),
            );
            $allowedDistance = match (true) {
                $longestLength <= 4 => 1,
                $longestLength <= 10 => 2,
                default => 3,
            };

            if (abs(Str::length($normalizedName) - Str::length($normalizedExistingName)) > $allowedDistance) {
                continue;
            }

            $distance = levenshtein($normalizedName, $normalizedExistingName);
            $similarity = 1 - ($distance / max($longestLength, 1));

            if ($distance > $allowedDistance || $similarity < 0.7 || $distance >= $closestDistance) {
                continue;
            }

            $closestCluster = $cluster;
            $closestDistance = $distance;
        }

        return $closestCluster;
    }

    private function normalize(string $name): string
    {
        return Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')
            ->squish()
            ->toString();
    }
}
