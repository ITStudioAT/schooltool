<?php

/**
 * Spa RouteAllowed Form Request Tests
 *
 * Tests for SPA routing permission request.
 */

use App\Http\Requests\Spa\RouteAllowedRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function validateRouteAllowedRequest(array $data): \Illuminate\Validation\Validator
{
    $request = new RouteAllowedRequest();
    return Validator::make($data, $request->rules());
}

describe('RouteAllowedRequest', function () {
    it('authorizes all requests', function () {
        $request = new RouteAllowedRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid admin route data', function () {
        $validator = validateRouteAllowedRequest([
            'data' => [
                'route' => 'admin',
                'from' => '/admin/dashboard',
                'to' => '/admin/users',
                'matching_path' => '/admin/users',
                'base_path' => '/admin',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with minimal data', function () {
        $validator = validateRouteAllowedRequest([
            'data' => [
                'route' => 'admin',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with empty data', function () {
        $validator = validateRouteAllowedRequest([]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when route has invalid value', function () {
        $validator = validateRouteAllowedRequest([
            'data' => [
                'route' => 'invalid_route',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('allows nullable from field', function () {
        $validator = validateRouteAllowedRequest([
            'data' => [
                'route' => 'admin',
                'from' => null,
                'to' => '/admin/users',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('allows nullable to field', function () {
        $validator = validateRouteAllowedRequest([
            'data' => [
                'route' => 'admin',
                'from' => '/admin/dashboard',
                'to' => null,
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('allows nullable matching_path field', function () {
        $validator = validateRouteAllowedRequest([
            'data' => [
                'route' => 'admin',
                'matching_path' => null,
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('allows nullable base_path field', function () {
        $validator = validateRouteAllowedRequest([
            'data' => [
                'route' => 'admin',
                'base_path' => null,
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('validates from as string', function () {
        $validator = validateRouteAllowedRequest([
            'data' => [
                'route' => 'admin',
                'from' => 123, // Should be string
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('validates to as string', function () {
        $validator = validateRouteAllowedRequest([
            'data' => [
                'route' => 'admin',
                'to' => ['not', 'a', 'string'], // Should be string
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
