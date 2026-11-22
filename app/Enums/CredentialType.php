<?php

namespace App\Enums;

/**
 * The kinds of secret FlitOps keeps for a server, stored encrypted in
 * server_credentials.
 */
enum CredentialType: string
{
    case PrivateKey = 'private_key';
    case PublicKey = 'public_key';
    case SudoPassword = 'sudo_password';
    case DatabasePassword = 'database_password';
    case RedisPassword = 'redis_password';
}
