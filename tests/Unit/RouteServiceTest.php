<?php

use App\Enums\RouteResult;
use App\Models\User;
use App\Services\RouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new RouteService();

    // Create roles for testing
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    // Create test route meta file
    $metaDir = base_path('routes/meta/web');
    if (!file_exists($metaDir)) {
        mkdir($metaDir, 0755, true);
    }

    // Create test route file (or overwrite if it exists)
    $testRouteFile = $metaDir . '/test.php';
    file_put_contents($testRouteFile, "<?php\n\nreturn [\n    'roles' => [\n        '/test/public' => [],\n        '/test/admin' => ['admin'],\n        '/test/teacher' => ['teacher'],\n        '/test/multi' => ['admin', 'teacher'],\n        '/test/prefix/*' => ['admin'],\n    ]\n];\n");

    // Clear file stat cache to ensure fresh reads
    clearstatcache();
});

describe('checkWebRoles', function () {
    
    describe('route group extraction', function () {
        
        it('extracts route group from path correctly', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            
            $result = $this->service->checkWebRoles($user, '/test/something');
            
            expect($result)->toBeInstanceOf(RouteResult::class);
        });
        
        it('handles empty path as homepage', function () {
            $user = User::factory()->create();
            
            $result = $this->service->checkWebRoles($user, '/');
            
            // homepage route file exists with public routes
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('handles path with leading slash', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            
            $result = $this->service->checkWebRoles($user, '/test/admin');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('handles path without leading slash', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            
            // Path 'test/admin' when trimmed and split gets 'test' as route group
            // But matchRouteRoles compares 'test/admin' to '/test/admin' which won't match
            $result = $this->service->checkWebRoles($user, 'test/admin');
            
            // Should return NOT_FOUND because path doesn't have leading slash for matching
            expect($result)->toBe(RouteResult::NOT_FOUND);
        });
        
        it('uses homepage as default for empty string', function () {
            $user = User::factory()->create();
            
            $result = $this->service->checkWebRoles($user, '');
            
            // homepage exists with public routes
            expect($result)->toBe(RouteResult::ALLOWED);
        });
    });
    
    describe('route file loading', function () {
        
        it('returns NOT_FOUND when route file does not exist', function () {
            $user = User::factory()->create();
            
            $result = $this->service->checkWebRoles($user, '/nonexistent/path');
            
            expect($result)->toBe(RouteResult::NOT_FOUND);
        });
        
        it('loads route file based on first path segment', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            
            $result = $this->service->checkWebRoles($user, '/test/admin');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
    });
    
    describe('route matching', function () {
        
        it('returns NOT_FOUND when route is not defined in meta file', function () {
            $user = User::factory()->create();
            
            $result = $this->service->checkWebRoles($user, '/test/undefined');
            
            expect($result)->toBe(RouteResult::NOT_FOUND);
        });
        
        it('returns ALLOWED for public routes without roles', function () {
            $user = User::factory()->create();
            
            $result = $this->service->checkWebRoles($user, '/test/public');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('returns ALLOWED for public routes without user', function () {
            $result = $this->service->checkWebRoles(null, '/test/public');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('returns ALLOWED when user has required role', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            
            $result = $this->service->checkWebRoles($user, '/test/admin');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('returns NOT_ALLOWED when user lacks required role', function () {
            $user = User::factory()->create();
            $user->assignRole('student');
            
            $result = $this->service->checkWebRoles($user, '/test/admin');
            
            expect($result)->toBe(RouteResult::NOT_ALLOWED);
        });
        
        it('returns ALLOWED when user has one of multiple required roles', function () {
            $user = User::factory()->create();
            $user->assignRole('teacher');
            
            $result = $this->service->checkWebRoles($user, '/test/multi');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('returns ALLOWED when super_admin accesses any protected route', function () {
            $user = User::factory()->create();
            $user->assignRole('super_admin');
            
            $result = $this->service->checkWebRoles($user, '/test/admin');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('returns NOT_EXISTS when no user and route requires auth', function () {
            $result = $this->service->checkWebRoles(null, '/test/admin');
            
            expect($result)->toBe(RouteResult::NOT_EXISTS);
        });
    });
    
    describe('wildcard matching', function () {
        
        it('matches wildcard routes with suffix path', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            
            $result = $this->service->checkWebRoles($user, '/test/prefix/something');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('matches wildcard routes at exact prefix', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            
            $result = $this->service->checkWebRoles($user, '/test/prefix');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('matches wildcard routes with deep nested paths', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            
            $result = $this->service->checkWebRoles($user, '/test/prefix/level1/level2/level3');
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('does not match wildcard when user lacks role', function () {
            $user = User::factory()->create();
            $user->assignRole('student');
            
            $result = $this->service->checkWebRoles($user, '/test/prefix/something');
            
            expect($result)->toBe(RouteResult::NOT_ALLOWED);
        });
    });
});

describe('matchRouteRoles', function () {
    
    it('returns roles for exact path match', function () {
        $roleMap = [
            '/admin/users' => ['admin'],
            '/admin/settings' => ['admin', 'teacher'],
        ];
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('matchRouteRoles');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, '/admin/users', $roleMap);
        
        expect($result)->toBe(['admin']);
    });
    
    it('returns null when no match found', function () {
        $roleMap = [
            '/admin/users' => ['admin'],
        ];
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('matchRouteRoles');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, '/admin/posts', $roleMap);
        
        expect($result)->toBeNull();
    });
    
    it('matches wildcard pattern with suffix', function () {
        $roleMap = [
            '/admin/*' => ['admin'],
        ];
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('matchRouteRoles');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, '/admin/users/create', $roleMap);
        
        expect($result)->toBe(['admin']);
    });
    
    it('matches wildcard pattern at exact prefix', function () {
        $roleMap = [
            '/admin/*' => ['admin'],
        ];
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('matchRouteRoles');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, '/admin', $roleMap);
        
        expect($result)->toBe(['admin']);
    });
    
    it('prefers exact match over wildcard', function () {
        $roleMap = [
            '/admin/*' => ['admin'],
            '/admin/public' => [],
        ];
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('matchRouteRoles');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, '/admin/public', $roleMap);
        
        expect($result)->toBe([]);
    });
    
    it('returns empty array for public routes', function () {
        $roleMap = [
            '/public/page' => [],
        ];
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('matchRouteRoles');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, '/public/page', $roleMap);
        
        expect($result)->toBe([]);
    });
    
    it('matches first wildcard when multiple patterns exist', function () {
        $roleMap = [
            '/admin/*' => ['admin'],
            '/admin/special/*' => ['super_admin'],
        ];
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('matchRouteRoles');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, '/admin/users', $roleMap);
        
        expect($result)->toBe(['admin']);
    });
});

describe('checkApiRoles', function () {
    
    describe('input validation', function () {
        
        it('returns NOT_FOUND when to path is missing', function () {
            $user = User::factory()->create();
            $route_roles = ['roles' => []];
            
            $result = $this->service->checkApiRoles($user, [], $route_roles);
            
            expect($result)->toBe(RouteResult::NOT_FOUND);
        });
        
        it('returns NOT_FOUND when to path is null', function () {
            $user = User::factory()->create();
            $route_roles = ['roles' => []];
            
            $result = $this->service->checkApiRoles($user, ['to' => null], $route_roles);
            
            expect($result)->toBe(RouteResult::NOT_FOUND);
        });
        
        it('defaults to GET method when not specified', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    'GET /users' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('converts method to uppercase', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    'POST /users' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users', 'method' => 'post'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
    });
    
    describe('path normalization', function () {
        
        it('removes /api prefix from path', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    'GET /users' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/api/users'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('handles path without /api prefix', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    'GET /users' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
    });
    
    describe('route matching', function () {
        
        it('returns NOT_EXISTS when route is not defined', function () {
            $user = User::factory()->create();
            $route_roles = ['roles' => []];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/undefined', 'method' => 'GET'], $route_roles);
            
            expect($result)->toBe(RouteResult::NOT_EXISTS);
        });
        
        it('returns ALLOWED for public routes without roles', function () {
            $user = User::factory()->create();
            $route_roles = [
                'roles' => [
                    'GET /public' => [],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/public'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('returns ALLOWED when user has required role', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    'POST /users' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users', 'method' => 'POST'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('returns NOT_ALLOWED when user lacks required role', function () {
            $user = User::factory()->create();
            $user->assignRole('student');
            $route_roles = [
                'roles' => [
                    'DELETE /users' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users', 'method' => 'DELETE'], $route_roles);
            
            expect($result)->toBe(RouteResult::NOT_ALLOWED);
        });
        
        it('returns NOT_ALLOWED when no user provided for protected route', function () {
            $route_roles = [
                'roles' => [
                    'GET /admin' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles(null, ['to' => '/admin'], $route_roles);
            
            expect($result)->toBe(RouteResult::NOT_ALLOWED);
        });
        
        it('returns ALLOWED when super_admin accesses any protected route', function () {
            $user = User::factory()->create();
            $user->assignRole('super_admin');
            $route_roles = [
                'roles' => [
                    'POST /admin/settings' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/admin/settings', 'method' => 'POST'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('returns ALLOWED when user has one of multiple required roles', function () {
            $user = User::factory()->create();
            $user->assignRole('teacher');
            $route_roles = [
                'roles' => [
                    'GET /courses' => ['admin', 'teacher'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/courses'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
    });
    
    describe('wildcard method matching', function () {
        
        it('matches any method with * wildcard', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    '* /admin' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/admin', 'method' => 'POST'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('matches GET with * wildcard', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    '* /users' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users', 'method' => 'GET'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('matches DELETE with * wildcard', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    '* /users/:id' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users/123', 'method' => 'DELETE'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
    });
    
    describe('path parameter matching', function () {
        
        it('matches route with :id parameter', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    'GET /users/:id' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users/123'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('matches route with multiple parameters', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    'GET /schools/:schoolId/users/:userId' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/schools/5/users/10'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
        
        it('does not match when path segment count differs', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    'GET /users/:id' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users/123/extra'], $route_roles);
            
            expect($result)->toBe(RouteResult::NOT_EXISTS);
        });
        
        it('matches numeric IDs with :id parameter', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $route_roles = [
                'roles' => [
                    'PATCH /users/:id' => ['admin'],
                ]
            ];
            
            $result = $this->service->checkApiRoles($user, ['to' => '/users/999', 'method' => 'PATCH'], $route_roles);
            
            expect($result)->toBe(RouteResult::ALLOWED);
        });
    });
});

describe('matchesPath', function () {
    
    it('matches exact paths', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/users', '/users');
        
        expect($result)->toBeTrue();
    });
    
    it('does not match different paths', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/users', '/posts');
        
        expect($result)->toBeFalse();
    });
    
    it('matches path with :id wildcard', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/users/:id', '/users/123');
        
        expect($result)->toBeTrue();
    });
    
    it('matches path with multiple wildcards', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/schools/:schoolId/users/:userId', '/schools/5/users/10');
        
        expect($result)->toBeTrue();
    });
    
    it('does not match when segment count differs', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/users/:id', '/users/123/extra');
        
        expect($result)->toBeFalse();
    });
    
    it('does not match when non-wildcard segments differ', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/users/:id', '/posts/123');
        
        expect($result)->toBeFalse();
    });
    
    it('handles paths with trailing slashes', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/users/:id/', '/users/123/');
        
        expect($result)->toBeTrue();
    });
    
    it('handles paths with leading slashes', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/users/:id', '/users/123');
        
        expect($result)->toBeTrue();
    });
    
    it('matches wildcard with string values', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/api/:resource', '/api/users');
        
        expect($result)->toBeTrue();
    });
    
    it('matches complex nested paths with wildcards', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesPath');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/api/:version/users/:id/posts/:postId', '/api/v1/users/5/posts/99');
        
        expect($result)->toBeTrue();
    });
});

describe('matchesRoute', function () {
    
    it('matches exact method and path', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, 'GET /users', 'GET /users');
        
        expect($result)->toBeTrue();
    });
    
    it('does not match different methods', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, 'GET /users', 'POST /users');
        
        expect($result)->toBeFalse();
    });
    
    it('does not match different paths', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, 'GET /users', 'GET /posts');
        
        expect($result)->toBeFalse();
    });
    
    it('matches wildcard method with any method', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, '* /users', 'POST /users');
        
        expect($result)->toBeTrue();
    });
    
    it('matches route with path parameters', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, 'GET /users/:id', 'GET /users/123');
        
        expect($result)->toBeTrue();
    });
    
    it('matches wildcard method with path parameters', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, '* /users/:id', 'DELETE /users/456');
        
        expect($result)->toBeTrue();
    });
    
    it('returns false when pattern is missing method', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, '/users', 'GET /users');
        
        expect($result)->toBeFalse();
    });
    
    it('returns false when route key is missing method', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, 'GET /users', '/users');
        
        expect($result)->toBeFalse();
    });
    
    it('is case insensitive for methods', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, 'get /users', 'GET /users');
        
        expect($result)->toBeTrue();
    });
    
    it('matches complex routes with multiple parameters', function () {
        $reflection = new ReflectionClass(RouteService::class);
        $method = $reflection->getMethod('matchesRoute');
        $method->setAccessible(true);
        $result = $method->invoke(null, 'PATCH /api/:version/users/:id', 'PATCH /api/v2/users/789');
        
        expect($result)->toBeTrue();
    });
});

describe('integration scenarios', function () {
    
    it('allows super_admin to access all web routes', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        
        $publicResult = $this->service->checkWebRoles($user, '/test/public');
        $adminResult = $this->service->checkWebRoles($user, '/test/admin');
        $teacherResult = $this->service->checkWebRoles($user, '/test/teacher');
        
        expect($publicResult)->toBe(RouteResult::ALLOWED)
            ->and($adminResult)->toBe(RouteResult::ALLOWED)
            ->and($teacherResult)->toBe(RouteResult::ALLOWED);
    });
    
    it('allows super_admin to access all API routes', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        
        $route_roles = [
            'roles' => [
                'GET /admin' => ['admin'],
                'POST /users' => ['admin'],
                'DELETE /posts/:id' => ['admin'],
            ]
        ];
        
        $getResult = $this->service->checkApiRoles($user, ['to' => '/admin'], $route_roles);
        $postResult = $this->service->checkApiRoles($user, ['to' => '/users', 'method' => 'POST'], $route_roles);
        $deleteResult = $this->service->checkApiRoles($user, ['to' => '/posts/5', 'method' => 'DELETE'], $route_roles);
        
        expect($getResult)->toBe(RouteResult::ALLOWED)
            ->and($postResult)->toBe(RouteResult::ALLOWED)
            ->and($deleteResult)->toBe(RouteResult::ALLOWED);
    });
    
    it('restricts user without roles from accessing protected routes', function () {
        $user = User::factory()->create();
        
        $webResult = $this->service->checkWebRoles($user, '/test/admin');
        
        $route_roles = ['roles' => ['GET /admin' => ['admin']]];
        $apiResult = $this->service->checkApiRoles($user, ['to' => '/admin'], $route_roles);
        
        expect($webResult)->toBe(RouteResult::NOT_ALLOWED)
            ->and($apiResult)->toBe(RouteResult::NOT_ALLOWED);
    });
    
    it('handles guest users correctly for public routes', function () {
        $webResult = $this->service->checkWebRoles(null, '/test/public');
        
        $route_roles = ['roles' => ['GET /public' => []]];
        $apiResult = $this->service->checkApiRoles(null, ['to' => '/public'], $route_roles);
        
        expect($webResult)->toBe(RouteResult::ALLOWED)
            ->and($apiResult)->toBe(RouteResult::ALLOWED);
    });
    
    it('handles guest users correctly for protected routes', function () {
        $webResult = $this->service->checkWebRoles(null, '/test/admin');
        
        $route_roles = ['roles' => ['GET /admin' => ['admin']]];
        $apiResult = $this->service->checkApiRoles(null, ['to' => '/admin'], $route_roles);
        
        expect($webResult)->toBe(RouteResult::NOT_EXISTS)
            ->and($apiResult)->toBe(RouteResult::NOT_ALLOWED);
    });
    
    it('handles user with multiple roles accessing routes', function () {
        $user = User::factory()->create();
        $user->assignRole(['admin', 'teacher']);
        
        $adminResult = $this->service->checkWebRoles($user, '/test/admin');
        $teacherResult = $this->service->checkWebRoles($user, '/test/teacher');
        
        expect($adminResult)->toBe(RouteResult::ALLOWED)
            ->and($teacherResult)->toBe(RouteResult::ALLOWED);
    });
});

describe('edge cases', function () {

    it('handles empty route_roles gracefully for web', function () {
        $user = User::factory()->create();

        // Create empty route file
        $emptyRouteFile = base_path('routes/meta/web/empty.php');
        file_put_contents($emptyRouteFile, "<?php\n\nreturn [];\n");

        $result = $this->service->checkWebRoles($user, '/empty/something');

        // Don't delete - Windows keeps file locked after include
        // File will be cleaned up by git or manually as it's in routes/meta/web

        expect($result)->toBe(RouteResult::NOT_FOUND);
    });
    
    it('handles empty route_roles gracefully for API', function () {
        $user = User::factory()->create();
        $route_roles = [];
        
        $result = $this->service->checkApiRoles($user, ['to' => '/users'], $route_roles);
        
        expect($result)->toBe(RouteResult::NOT_EXISTS);
    });
    
    it('handles malformed API data gracefully', function () {
        $user = User::factory()->create();
        $route_roles = ['roles' => []];
        
        $result = $this->service->checkApiRoles($user, ['invalid' => 'data'], $route_roles);
        
        expect($result)->toBe(RouteResult::NOT_FOUND);
    });
    
    it('handles very long paths', function () {
        $user = User::factory()->create();
        $longPath = '/test/' . str_repeat('segment/', 50) . 'end';
        
        $result = $this->service->checkWebRoles($user, $longPath);
        
        expect($result)->toBe(RouteResult::NOT_FOUND);
    });
    
    it('handles paths with special characters', function () {
        $user = User::factory()->create();
        
        $result = $this->service->checkWebRoles($user, '/test/path-with-dash');
        
        expect($result)->toBeInstanceOf(RouteResult::class);
    });
    
    it('handles case sensitivity in paths', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        // Routes are case-sensitive
        $result = $this->service->checkWebRoles($user, '/TEST/admin');
        
        expect($result)->toBe(RouteResult::NOT_FOUND);
    });
});

