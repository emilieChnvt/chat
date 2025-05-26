<?php

namespace App\Service;

use App\Entity\Profile;
use App\Entity\User;
use App\Repository\UserRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Hmac\Sha256;

class MercureJWTGenerator
{
    private string $mercureSecret;

    public function __construct(
        string $mercureSecret
    )
    {
        $this->mercureSecret = $mercureSecret;
    }
    public function generate(Profile $profile): string
    {
        if(!$profile){throw new \Exception('User not connected');}


        $allowedTopics = [];

        foreach ($profile->getConversations() as $conversation) {

            $allowedTopics[]= 'conversations/' . $conversation->getId();

        }
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->mercureSecret)
        );
        $tokenBuilder = $config->builder();
        $token = $tokenBuilder
            ->withClaim('mercure', [
                'subscribe'=>$allowedTopics,
                'publish'=>$allowedTopics
            ])
            ->issuedAt((new \DateTimeImmutable()))
            ->expiresAt((new \DateTimeImmutable())->modify('+1 hour'))
            ->getToken(new Sha256(), InMemory::plainText($this->mercureSecret));

        return $token->toString();
    }

}