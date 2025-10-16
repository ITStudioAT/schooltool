<?php

namespace App\Traits;

trait PaginationTrait
{
    public function makePagination($pagination): array
    {

        $nextPage = $pagination->currentPage() == $pagination->lastPage() ? null : $pagination->currentPage() + 1;
        $prevPage = $pagination->currentPage() == 1 ? null : $pagination->currentPage() - 1;

        $data = [
            'pagination' => [
                'current_page' => $pagination->currentPage(),
                'last_page' => $pagination->lastPage(),
                'next_page' => $nextPage,
                'prev_page' => $prevPage,
                'per_page' => $pagination->perPage(),
                'total' => $pagination->total(),
            ],
            'items' => $pagination->items(),
        ];

        return $data;
    }
}
