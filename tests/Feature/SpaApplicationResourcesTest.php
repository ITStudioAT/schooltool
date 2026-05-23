<?php

it('serves spa namespaced views from application resources', function (): void {
    expect(view()->exists('spa::admin'))->toBeTrue()
        ->and(view()->exists('spa::application'))->toBeTrue()
        ->and(view()->exists('spa::mails.admin.sendCode'))->toBeTrue()
        ->and(view()->exists('spa::mails.homepage.sendSepaMandate'))->toBeTrue();
});
