<?php

namespace App\Services\WebAuthn;

use App\Models\User;
use App\Models\WebAuthnChallenge;
use App\Models\WebAuthnCredential;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Illuminate\Support\Str;
use RuntimeException;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class WebAuthnService
{
    public const RP_ID = 'crypticxlab.duckdns.org';

    public const ORIGIN = 'https://crypticxlab.duckdns.org';

    public const CHALLENGE_TTL_MINUTES = 5;

    private function serializer()
    {
        $attestationManager = new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ]);

        return (new WebauthnSerializerFactory(
            $attestationManager
        ))->create();
    }

    public function createRegistrationOptions(
        User $user
    ): array {
        $challenge = random_bytes(32);

        $rp = PublicKeyCredentialRpEntity::create(
            'CrypticX Lab',
            self::RP_ID
        );

        $webAuthnUser = PublicKeyCredentialUserEntity::create(
            strtolower($user->email),
            (string) $user->id,
            $user->name ?: $user->email
        );

        $selection = AuthenticatorSelectionCriteria::create(
            authenticatorAttachment:
                AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
            residentKey:
                AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            userVerification:
                AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED
        );

        $options = PublicKeyCredentialCreationOptions::create(
            rp: $rp,
            user: $webAuthnUser,
            challenge: $challenge,
            pubKeyCredParams: [
                PublicKeyCredentialParameters::create(
                    'public-key',
                    ES256::ID
                ),
                PublicKeyCredentialParameters::create(
                    'public-key',
                    RS256::ID
                ),
            ],
            authenticatorSelection: $selection,
            attestation:
                PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            timeout: 60000
        );

        $serializer = $this->serializer();

        $serialized = $serializer->serialize(
            $options,
            'json'
        );

        $challengeRow = WebAuthnChallenge::create([
            'user_id' => $user->id,
            'purpose' => WebAuthnChallenge::PURPOSE_REGISTER,
            'challenge' => rtrim(
                strtr(
                    base64_encode($challenge),
                    '+/',
                    '-_'
                ),
                '='
            ),
            'options_json' => $serialized,
            'expires_at' => now()->addMinutes(
                self::CHALLENGE_TTL_MINUTES
            ),
        ]);

        return [
            'transaction_id' => $challengeRow->id,
            'public_key' => json_decode(
                $serialized,
                true,
                flags: JSON_THROW_ON_ERROR
            ),
        ];
    }

    public function verifyRegistration(
        User $user,
        string $transactionId,
        array $credential
    ): WebAuthnCredential {
        $challenge = WebAuthnChallenge::query()
            ->whereKey($transactionId)
            ->where('user_id', $user->id)
            ->where(
                'purpose',
                WebAuthnChallenge::PURPOSE_REGISTER
            )
            ->firstOrFail();

        if (! $challenge->isUsable()) {
            throw new RuntimeException(
                'WebAuthn registration challenge expired or already used.'
            );
        }

        $serializer = $this->serializer();

        $options = $serializer->deserialize(
            $challenge->options_json,
            PublicKeyCredentialCreationOptions::class,
            'json'
        );

        $publicKeyCredential = $serializer->deserialize(
            json_encode(
                $credential,
                JSON_THROW_ON_ERROR
            ),
            PublicKeyCredential::class,
            'json'
        );

        if (
            ! $publicKeyCredential->response
                instanceof AuthenticatorAttestationResponse
        ) {
            throw new RuntimeException(
                'Invalid WebAuthn attestation response.'
            );
        }

        $factory = new CeremonyStepManagerFactory();

        $factory->setAllowedOrigins([
            self::ORIGIN,
        ]);

        $validator =
            AuthenticatorAttestationResponseValidator::create(
                $factory->creationCeremony()
            );

        $record = $validator->check(
            $publicKeyCredential->response,
            $options,
            self::RP_ID
        );

        if ($record->uvInitialized !== true) {
            throw new RuntimeException(
                'Authenticator did not perform required user verification.'
            );
        }

        $credentialId = rtrim(
            strtr(
                base64_encode(
                    $record->publicKeyCredentialId
                ),
                '+/',
                '-_'
            ),
            '='
        );

        $model = WebAuthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'credential_id_hash' => hash(
                'sha256',
                $record->publicKeyCredentialId
            ),
            'credential_source' =>
                $serializer->serialize(
                    $record,
                    'json'
                ),
            'name' => 'Platform Passkey',
            'transports' => $record->transports,
            'aaguid' => (string) $record->aaguid,
            'sign_count' => $record->counter,
        ]);

        $challenge->forceFill([
            'used_at' => now(),
        ])->save();

        return $model;
    }


    public function createAuthenticationOptions(User $user): array
    {
        $credentials = $user->webAuthnCredentials()
            ->whereNull('revoked_at')
            ->get();

        if ($credentials->isEmpty()) {
            throw new RuntimeException(
                'No active passkey is configured for this administrator.'
            );
        }

        $serializer = $this->serializer();

        $allowCredentials = $credentials
            ->map(function (WebAuthnCredential $credential) use ($serializer) {
                /** @var CredentialRecord $record */
                $record = $serializer->deserialize(
                    $credential->credential_source,
                    CredentialRecord::class,
                    'json'
                );

                return $record->getPublicKeyCredentialDescriptor();
            })
            ->values()
            ->all();

        $challengeBytes = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challengeBytes,
            rpId: self::RP_ID,
            allowCredentials: $allowCredentials,
            userVerification:
                PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 60000,
        );

        $serialized = $serializer->serialize(
            $options,
            'json'
        );

        $challengeRow = WebAuthnChallenge::create([
            'user_id' => $user->id,
            'purpose' => WebAuthnChallenge::PURPOSE_AUTHENTICATE,
            'challenge' => rtrim(
                strtr(
                    base64_encode($challengeBytes),
                    '+/',
                    '-_'
                ),
                '='
            ),
            'options_json' => $serialized,
            'expires_at' => now()->addMinutes(
                self::CHALLENGE_TTL_MINUTES
            ),
        ]);

        return [
            'transaction_id' => $challengeRow->id,
            'public_key' => json_decode(
                $serialized,
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
        ];
    }

    public function verifyAuthentication(
        User $user,
        string $transactionId,
        array $credential
    ): WebAuthnCredential {
        $challenge = WebAuthnChallenge::query()
            ->whereKey($transactionId)
            ->where('user_id', $user->id)
            ->where(
                'purpose',
                WebAuthnChallenge::PURPOSE_AUTHENTICATE
            )
            ->firstOrFail();

        if (! $challenge->isUsable()) {
            throw new RuntimeException(
                'WebAuthn authentication challenge expired or already used.'
            );
        }

        $serializer = $this->serializer();

        /** @var PublicKeyCredentialRequestOptions $options */
        $options = $serializer->deserialize(
            $challenge->options_json,
            PublicKeyCredentialRequestOptions::class,
            'json'
        );

        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = $serializer->deserialize(
            json_encode(
                $credential,
                JSON_THROW_ON_ERROR
            ),
            PublicKeyCredential::class,
            'json'
        );

        if (
            ! $publicKeyCredential->response
                instanceof AuthenticatorAssertionResponse
        ) {
            throw new RuntimeException(
                'Invalid WebAuthn assertion response.'
            );
        }

        $credentialModel = WebAuthnCredential::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(
                'credential_id_hash',
                hash(
                    'sha256',
                    $publicKeyCredential->rawId
                )
            )
            ->first();

        if (! $credentialModel) {
            throw new RuntimeException(
                'Passkey credential is not registered for this administrator.'
            );
        }

        /** @var CredentialRecord $record */
        $record = $serializer->deserialize(
            $credentialModel->credential_source,
            CredentialRecord::class,
            'json'
        );

        $factory = new CeremonyStepManagerFactory();

        $factory->setAllowedOrigins([
            self::ORIGIN,
        ]);

        $validator =
            AuthenticatorAssertionResponseValidator::create(
                $factory->requestCeremony()
            );

        $updatedRecord = $validator->check(
            $record,
            $publicKeyCredential->response,
            $options,
            self::RP_ID,
            $user->id
        );

        if (
            ! $publicKeyCredential->response
                ->authenticatorData
                ->isUserVerified()
        ) {
            throw new RuntimeException(
                'Authenticator did not perform required user verification.'
            );
        }

        $credentialModel->forceFill([
            'credential_source' => $serializer->serialize(
                $updatedRecord,
                'json'
            ),
            'sign_count' => $updatedRecord->counter,
            'last_used_at' => now(),
        ])->save();

        $challenge->forceFill([
            'used_at' => now(),
        ])->save();

        return $credentialModel->fresh();
    }

}
