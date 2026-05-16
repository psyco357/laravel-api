<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class JwtService
{
    public function issueTokenPair(User $user, int $appId, ?string $deviceName, ?Request $request = null): array
    {
        $sessionId = (string) Str::uuid();
        $now = now();
        $accessExpiresAt = $now->copy()->addMinutes($this->accessTokenTtl());
        $refreshExpiresAt = $now->copy()->addDays($this->refreshTokenTtl());

        $role = $this->resolveRoleName($user->id, $appId);

        $refreshToken = Str::random(80);

        RefreshToken::create([
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'app_id' => $appId,
            'token_hash' => hash('sha256', $refreshToken),
            'device_name' => $deviceName ?: 'api',
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'expires_at' => $refreshExpiresAt,
        ]);

        return [
            'token_type' => 'Bearer',
            'access_token' => $this->encodeAccessToken($user, $appId, $sessionId, $role, $accessExpiresAt, $now),
            'expires_in' => $accessExpiresAt->diffInSeconds($now),
            'refresh_token' => $refreshToken,
            'refresh_token_expires_at' => $refreshExpiresAt->toIso8601String(),
            'session_id' => $sessionId,
        ];
    }

    public function authenticate(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (!$token) {
            return null;
        }

        $claims = $this->decodeAccessToken($token);

        if (($claims['type'] ?? null) !== 'access') {
            return null;
        }

        $user = User::query()
            ->whereKey((int) ($claims['sub'] ?? 0))
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return null;
        }

        $hasAccess = DB::connection('central')
            ->table('user_has_apps')
            ->where('user_id', $user->id)
            ->where('app_id', (int) ($claims['app_id'] ?? 0))
            ->where('is_active', true)
            ->exists();

        if (!$hasAccess) {
            return null;
        }

        $user->setAttribute('jwt_claims', $claims);
        $user->setAttribute('role', $claims['role'] ?? null);
        $user->setAttribute('active_app_id', (int) ($claims['app_id'] ?? 0));

        $request->attributes->set('jwt_claims', $claims);

        return $user;
    }

    public function refreshToken(string $plainTextToken, int $appId, ?string $deviceName, ?Request $request = null): array
    {
        $hashedToken = hash('sha256', $plainTextToken);

        /** @var RefreshToken|null $storedToken */
        $storedToken = RefreshToken::query()
            ->where('token_hash', $hashedToken)
            ->where('app_id', $appId)
            ->first();

        if (!$storedToken || $storedToken->revoked_at !== null || $storedToken->expires_at->isPast()) {
            throw new UnauthorizedHttpException('Bearer', 'Refresh token is invalid or expired.');
        }

        $user = User::query()
            ->whereKey($storedToken->user_id)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            throw new UnauthorizedHttpException('Bearer', 'User is inactive or no longer exists.');
        }

        $storedToken->forceFill([
            'last_used_at' => now(),
            'revoked_at' => now(),
        ])->save();

        return $this->issueTokenPair($user, $appId, $deviceName ?: $storedToken->device_name, $request);
    }

    public function revokeSession(?string $sessionId, int $userId): void
    {
        if (!$sessionId) {
            return;
        }

        RefreshToken::query()
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function decodeAccessToken(string $token): array
    {
        try {
            [$encodedHeader, $encodedPayload, $encodedSignature] = explode('.', $token);
        } catch (\Throwable $exception) {
            throw new UnauthorizedHttpException('Bearer', 'Access token is invalid or expired.', $exception);
        }

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->signingKey(), true)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            throw new UnauthorizedHttpException('Bearer', 'Access token signature is invalid.');
        }

        try {
            $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnauthorizedHttpException('Bearer', 'Access token payload is invalid.', $exception);
        }

        if (!is_array($payload)) {
            throw new UnauthorizedHttpException('Bearer', 'Access token payload is invalid.');
        }

        $now = now()->timestamp;

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            throw new UnauthorizedHttpException('Bearer', 'Access token is not valid yet.');
        }

        if (!isset($payload['exp']) || (int) $payload['exp'] <= $now) {
            throw new UnauthorizedHttpException('Bearer', 'Access token is invalid or expired.');
        }

        return $payload;
    }

    private function encodeAccessToken(
        User $user,
        int $appId,
        string $sessionId,
        ?string $role,
        Carbon $expiresAt,
        Carbon $issuedAt
    ): string {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'app_id' => $appId,
            'role' => $role,
            'sid' => $sessionId,
            'type' => 'access',
            'iat' => $issuedAt->timestamp,
            'nbf' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
            'jti' => (string) Str::uuid(),
        ];

        try {
            $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
            $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode JWT payload.', 0, $exception);
        }

        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->signingKey(), true);

        return $encodedHeader . '.' . $encodedPayload . '.' . $this->base64UrlEncode($signature);
    }

    private function resolveRoleName(int $userId, int $appId): ?string
    {
        return DB::connection('central')
            ->table('user_has_apps')
            ->leftJoin('role', 'role.id', '=', 'user_has_apps.role_id')
            ->where('user_has_apps.user_id', $userId)
            ->where('user_has_apps.app_id', $appId)
            ->value('role.name');
    }

    private function accessTokenTtl(): int
    {
        return (int) config('services.jwt.access_token_ttl', 15);
    }

    private function refreshTokenTtl(): int
    {
        return (int) config('services.jwt.refresh_token_ttl_days', 30);
    }

    private function signingKey(): string
    {
        $secret = config('services.jwt.secret');

        if (!is_string($secret) || $secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('JWT secret is not valid base64 data.');
            }

            return $decoded;
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Failed to decode base64url value.');
        }

        return $decoded;
    }
}
