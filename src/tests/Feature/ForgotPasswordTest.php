<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    public function test_that_forgot_password_is_available()
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_that_only_ajax_requests_are_handled()
    {
        $response = $this->post(
                                '/forgot-password',
                                [ 'email' => '' ]
                            );

        $response
            ->assertStatus(500);
    }

    public function test_that_empty_email_is_rejected()
    {
        $response = $this->withHeaders([ 
                                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                                'Accept' => 'application/json',
                            ])->post(
                                '/forgot-password',
                                [ 'email' => '' ]
                            );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.email.0',
                trans('validation.required', [ 'attribute' => 'email' ])
            );
    }

    public function test_that_invalid_email_is_rejected()
    {
        $response = $this->withHeaders([ 
                                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                                'Accept' => 'application/json',
                            ])->post(
                                '/forgot-password',
                                [ 'email' => 'aaa' ]
                            );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.email.0',
                trans('validation.email', [ 'attribute' => 'email' ])
            );
    }

    public function test_that_token_generation_fails_for_unknown_user()
    {
        $response = $this->withHeaders([ 
                                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                                'Accept' => 'application/json',
                            ])->post(
                                '/forgot-password',
                                [ 'email' => 'x@x.x' ]
                            );

        $response
            ->assertStatus(500)
            ->assertJsonPath(
                'displayMessage',
                trans('auth.login-link.generation.failed')
            );
    }

    public function test_that_login_link_is_sent_to_user()
    {
        $user = User::firstOrCreate([ 'email' => 'a@b.c' ]);

        $response = $this->withHeaders([ 
                                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                                'Accept' => 'application/json',
                            ])->post(
                                '/forgot-password',
                                [ 'email' => $user->email ]
                            );

        $response
            ->assertStatus(200)
            ->assertJsonPath(
                'displayMessage',
                trans('auth.login-link.sending.success')
            );
    }
}
