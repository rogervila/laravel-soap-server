<?php

namespace Tests\LaravelSoapServer\Stubs;

use stdClass;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;


class UserService
{
    /**
     * @return array{status: string, message: string, data?: array<string, mixed>}
     */
    public function createUser(stdClass $request): array
    {
        try {
            $validation = Validator::make([
                'name' => $request->name ?? null,
                'email' => $request->email ?? null,
            ], [
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'email' => ['required', 'email'],
            ]);

            if (!$validation->passes()) {
                return [
                    'status' => '422',
                    'message' => $validation->errors()->toJson(),
                ];
            }

            $payload = $validation->validated();

            if (User::query()->where(['email' => $payload['email']])->exists()) {
                return [
                    'status' => '400',
                    'message' => 'User already exists',
                ];
            }

            $user = User::query()->create(array_merge($payload, ['password' => Str::random()]));

            // Handle password reset notification here

            return [
                'status' => '201',
                'message' => 'User created successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at->toDateTimeString(),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'status' => '500',
                'message' => $e->getMessage(),
            ];
        }
    }
}
