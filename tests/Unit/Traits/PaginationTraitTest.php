<?php

use App\Traits\PaginationTrait;
use Illuminate\Pagination\LengthAwarePaginator;

test('pagination trait maps pagination metadata and items', function () {
    $paginator = new LengthAwarePaginator([1, 2, 3], 3, 2, 1);

    $subject = new class {
        use PaginationTrait;
    };

    $data = $subject->makePagination($paginator);

    expect($data['pagination']['current_page'])->toBe(1)
        ->and($data['pagination']['last_page'])->toBe(2)
        ->and($data['pagination']['next_page'])->toBe(2)
        ->and($data['pagination']['prev_page'])->toBeNull()
        ->and($data['pagination']['per_page'])->toBe(2)
        ->and($data['pagination']['total'])->toBe(3)
        ->and($data['items'])->toBe([1, 2, 3]);
});

test('pagination trait returns null next_page on last page', function () {
    $paginator = new LengthAwarePaginator([1], 1, 1, 1);

    $subject = new class {
        use PaginationTrait;
    };

    $data = $subject->makePagination($paginator);

    expect($data['pagination']['next_page'])->toBeNull()
        ->and($data['pagination']['prev_page'])->toBeNull();
});
