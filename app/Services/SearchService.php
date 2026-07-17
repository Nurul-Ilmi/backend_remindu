<?php

namespace App\Services;

use App\Models\User;

class SearchService
{
    public function search(User $user, string $query): array
    {
        if (empty($query)) {
            return ['tasks' => [], 'groups' => []];
        }

        $tasks = $user->tasks()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        $groups = $user->groups()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('subject', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        return [
            'tasks' => $tasks,
            'groups' => $groups,
        ];
    }
}
