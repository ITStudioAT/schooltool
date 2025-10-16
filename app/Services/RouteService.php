<?php

namespace App\Services;

use App\Enums\RouteResult;

class RouteService
{
    public function checkWebRoles($user, $fullPath): RouteResult
    {
        // Lade Route-Metadaten basierend auf erster URI-Sektion
        $routeGroup = explode('/', trim($fullPath, '/'))[0] ?? 'homepage';
        if ($routeGroup == "") $routeGroup = "homepage";

        $routeFile = base_path('routes/meta/web/' . $routeGroup . '.php');
        $route_roles = file_exists($routeFile) ? include $routeFile : [];

        $roles = $this->matchRouteRoles($fullPath, $route_roles['roles'] ?? []);

        // super_admin zu den erlaubten Rollen hinzufügen
        // $roles[] = 'super_admin';

        if (is_null($roles)) return RouteResult::NOT_FOUND;
        if (empty($roles)) return RouteResult::ALLOWED;
        if ($user && ($user->hasAnyRole($roles) || $user->hasRole('super_admin'))) return RouteResult::ALLOWED;
        if ($user) return RouteResult::NOT_ALLOWED;

        return RouteResult::NOT_EXISTS;
    }

    protected function matchRouteRoles(string $path, array $roleMap): ?array
    {
        // 1. Exakter Match
        if (array_key_exists($path, $roleMap)) {
            return $roleMap[$path];
        }

        // 2. Wildcard-Match (z. B. '/admin/*' deckt auch '/admin' ab)
        foreach ($roleMap as $pattern => $roles) {
            if (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -2); // Entfernt nur das '/*' am Ende
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    return $roles;
                }
            }
        }

        // 3. Kein Match gefunden
        return null;
    }

    public function checkApiRoles($user, $data, $route_roles): RouteResult
    {

        $toPath = $data['to'] ?? null;  // z.B. '/api/admin/users/7'
        $method = strtoupper($data['method'] ?? 'GET'); // HTTP-Methode

        if (! $toPath) {
            return RouteResult::NOT_FOUND;
        }

        // Entferne '/api' aus dem Pfad, wenn es vorhanden ist
        if (str_starts_with($toPath, '/api')) {
            $toPath = substr($toPath, 4); // Entferne die ersten 4 Zeichen ('/api')
        }

        $rolesMap = $route_roles['roles'] ?? [];
        $routeKey = $method . ' ' . $toPath; // z.B. "POST /admin/users/7"

        $matchedKey = null;

        // Suche nach einer Übereinstimmung der Route und Methode
        foreach ($rolesMap as $pattern => $roles) {
            if (self::matchesRoute($pattern, $routeKey)) {
                $matchedKey = $pattern;

                break;
            }
        }

        // Wenn keine Übereinstimmung gefunden wurde
        if (! $matchedKey) {
            return RouteResult::NOT_EXISTS;
        }

        $requiredRoles = $rolesMap[$matchedKey];

        // Wenn keine Rollen erforderlich sind, erlauben
        if (empty($requiredRoles)) {
            return RouteResult::ALLOWED;
        }

        // Wenn kein Benutzer eingeloggt ist, nicht erlauben
        if (! $user) {
            return RouteResult::NOT_ALLOWED;
        }

        // Wenn super_admin in der Konfiguration gesetzt ist, füge ihn zu den erforderlichen Rollen hinzu
        $requiredRoles[] = 'super_admin';

        // Überprüfe, ob der Benutzer die erforderlichen Rollen hat
        if ($user->hasAnyRole($requiredRoles)) {
            return RouteResult::ALLOWED;
        }

        return RouteResult::NOT_ALLOWED;
    }

    private static function matchesPath(string $pattern, string $path): bool
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));

        if (count($patternParts) !== count($pathParts)) {
            return false;
        }

        foreach ($patternParts as $i => $part) {
            if (str_starts_with($part, ':')) {
                continue; // wildcard segment, like :id
            }

            if ($part !== $pathParts[$i]) {
                return false;
            }
        }

        return true;
    }

    private static function matchesRoute(string $pattern, string $routeKey): bool
    {
        $patternParts = explode(' ', $pattern, 2);
        $routeParts = explode(' ', $routeKey, 2);

        if (count($patternParts) < 2 || count($routeParts) < 2) {
            return false; // Beide müssen "METHOD PATH" Format haben
        }

        [$patternMethod, $patternPath] = $patternParts;
        [$routeMethod, $routePath] = $routeParts;

        // Methode vergleichen (z.B. GET vs POST), '*' erlaubt alles
        if ($patternMethod !== '*' && strtoupper($patternMethod) !== strtoupper($routeMethod)) {
            return false;
        }

        // Path vergleichen (mit Wildcards wie :id)
        return self::matchesPath($patternPath, $routePath);
    }
}
