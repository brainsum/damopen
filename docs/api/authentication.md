# Authentication

## Overview

DAM Open API uses HTTP Basic Authentication for securing API access. Authentication is handled through the `BasicAuthWithExclude` provider which extends Drupal's standard Basic Auth.

## Authentication Methods

### Basic Authentication

The API uses standard HTTP Basic Authentication. You need to include an `Authorization` header with each request.

```
Authorization: Basic {base64_encoded_credentials}
```

Where `{base64_encoded_credentials}` is the Base64 encoding of `username:password`.

### Example

```php
$username = 'api_user';
$password = 'api_password';
$auth_header = 'Authorization: Basic ' . base64_encode("$username:$password");
```

## Custom Authentication Provider

DAM Open extends Drupal's Basic Authentication with a custom provider (`BasicAuthWithExclude`) that:

1. Applies authentication only to JSON:API routes
2. Handles LDAP integration for user authentication
3. Provides exclusion mechanisms for specific routes

## Required Permissions

To access the API endpoints, the authenticated user must have the following permission:

- `access tml jsonapi resources`

## Security Best Practices

1. **Use HTTPS**: Always use HTTPS for API communication to encrypt credentials
2. **Dedicated API User**: Create a dedicated user with minimal permissions for API access
3. **Credential Management**: Store API credentials securely and never hardcode them
4. **Token Rotation**: Regularly change API user passwords
5. **IP Restrictions**: Consider restricting API access to specific IP addresses
