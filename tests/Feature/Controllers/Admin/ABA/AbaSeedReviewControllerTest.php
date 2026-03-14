<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);

    $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
    File::ensureDirectoryExists($this->proposalsDir);
});

function createUserWithRole(string $role): User
{
    $user = User::factory()->create([
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

function sampleReplacementDraft(string $title = 'Testtitel'): string
{
    return <<<MD
---
title: "{$title}"
status: draft
version: "1.0.0"
draft_type: seed-replacement
draft_generated_at: "2026-03-14"
requires_review: true
---
# {$title}

## 1. Einleitung
Text A.

## 2. Hinweise
Text B.

## 3. Schluss
Text C.
MD;
}

test('admin can save editable proposal content', function () {
    $user = createUserWithRole('admin');
    $filename = 'seed-replacement-draft-test-'.Str::lower(Str::random(8)).'.md';
    $path = $this->proposalsDir.'/'.$filename;
    $updatedContent = sampleReplacementDraft('Aktualisierter Titel');
    File::put($path, sampleReplacementDraft('Ausgangstitel'));

    try {
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/aba/seed-review/content/{$filename}", [
                'content' => $updatedContent,
            ])
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('filename', $filename);

        expect(File::get($path))->toBe($updatedContent);
    } finally {
        if (File::exists($path)) {
            File::delete($path);
        }
    }
});

test('super admin can save editable proposal content', function () {
    $user = createUserWithRole('super_admin');
    $filename = 'seed-replacement-draft-test-'.Str::lower(Str::random(8)).'.md';
    $path = $this->proposalsDir.'/'.$filename;
    $updatedContent = sampleReplacementDraft('Superadmin-Version');
    File::put($path, sampleReplacementDraft('Ausgangstitel'));

    try {
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/aba/seed-review/content/{$filename}", [
                'content' => $updatedContent,
            ])
            ->assertSuccessful()
            ->assertJsonPath('success', true);
    } finally {
        if (File::exists($path)) {
            File::delete($path);
        }
    }
});

test('save endpoint rejects analysis report files', function () {
    $user = createUserWithRole('admin');
    $filename = 'ai-seed-hardening-draft-test-'.Str::lower(Str::random(8)).'.md';
    $path = $this->proposalsDir.'/'.$filename;
    File::put($path, "# Analysebericht\n\nNicht editierbar.");

    try {
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/aba/seed-review/content/{$filename}", [
                'content' => "# Neuer Inhalt\n\nText",
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    } finally {
        if (File::exists($path)) {
            File::delete($path);
        }
    }
});

test('save endpoint rejects empty proposal content', function () {
    $user = createUserWithRole('admin');
    $filename = 'seed-replacement-draft-test-'.Str::lower(Str::random(8)).'.md';
    $path = $this->proposalsDir.'/'.$filename;
    $originalContent = sampleReplacementDraft('Ausgangstitel');
    File::put($path, $originalContent);

    try {
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/aba/seed-review/content/{$filename}", [
                'content' => "   \n\n",
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        expect(File::get($path))->toBe($originalContent);
    } finally {
        if (File::exists($path)) {
            File::delete($path);
        }
    }
});

test('aba teacher cannot save proposal content', function () {
    $user = createUserWithRole('aba_teacher');
    $filename = 'seed-replacement-draft-test-'.Str::lower(Str::random(8)).'.md';
    $path = $this->proposalsDir.'/'.$filename;
    File::put($path, sampleReplacementDraft('Ausgangstitel'));

    try {
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/aba/seed-review/content/{$filename}", [
                'content' => sampleReplacementDraft('Nicht erlaubt'),
            ])
            ->assertForbidden();
    } finally {
        if (File::exists($path)) {
            File::delete($path);
        }
    }
});

test('review page keeps proposal generation as special case and not as main action', function () {
    $content = file_get_contents(resource_path('js/pages/admin/aba/AbaSeedReview.vue'));

    expect($content)
        ->toContain('Analysieren &amp; neuen Vorschlag erstellen')
        ->toContain('Spezialfall: Vorschlag manuell neu erzeugen')
        ->toContain('Zur Hauptdatei-Seite')
        ->not->toContain('>Vorschlag erstellen<');
});

test('seed report marks separate proposal action as special case', function () {
    $content = file_get_contents(resource_path('js/pages/admin/aba/AbaSeedReport.vue'));

    expect($content)
        ->toContain('Spezialfall: Vorschlag aus Online-Prüfung erstellen')
        ->toContain('Analyse abgeschlossen · Neuer Vorschlag liegt vor');
});

test('seed report uses rules and safety wording instead of governance', function () {
    $content = file_get_contents(resource_path('js/pages/admin/aba/AbaSeedReport.vue'));

    expect($content)
        ->toContain('Regeln &amp; Sicherheit')
        ->toContain('Hinweis zu Regeln &amp; Sicherheit:')
        ->not->toContain('>Governance<')
        ->not->toContain('<strong>Governance:</strong>');
});

test('seed report shows clear source status wording after analysis', function () {
    $content = file_get_contents(resource_path('js/pages/admin/aba/AbaSeedReport.vue'));

    expect($content)
        ->toContain('Quellen vollständig geklärt')
        ->toContain('Quellen noch offen')
        ->toContain('Teilweise geklärt (Zwischenstand)')
        ->toContain('Offene Quellenpunkte');
});
