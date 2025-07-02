# Integration Examples

This document provides code examples for integrating with the DAM Open API using different programming languages and frameworks.

## PHP Examples

### Basic API Request

```php
<?php

/**
 * Fetch media assets from DAM Open.
 */
function fetch_dam_assets($base_url, $username, $password) {
  $endpoint = $base_url . '/jsonapi/media/image';
  $ch = curl_init($endpoint);
  
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/vnd.api+json',
    'Authorization: Basic ' . base64_encode("$username:$password")
  ]);
  
  $response = curl_exec($ch);
  curl_close($ch);
  
  return json_decode($response, true);
}

/**
 * Display an image from DAM Open with a specific style.
 */
function display_dam_image($image_uri, $style = 'medium', $base_url, $username, $password) {
  $image_url = $base_url . '/image-style/' . $style . '/private/' . $image_uri;
  
  // Create context with authentication
  $context = stream_context_create([
    'http' => [
      'header' => 'Authorization: Basic ' . base64_encode("$username:$password")
    ]
  ]);
  
  // Return image tag with authenticated URL
  return '<img src="' . $image_url . '" alt="DAM Image" />';
}
```

### Using Guzzle HTTP Client

```php
<?php

use GuzzleHttp\Client;

/**
 * Fetch media assets using Guzzle.
 */
function fetch_dam_assets_guzzle($base_url, $username, $password) {
  $client = new Client();
  
  $response = $client->request('GET', $base_url . '/jsonapi/media/image', [
    'headers' => [
      'Accept' => 'application/vnd.api+json',
      'Authorization' => 'Basic ' . base64_encode("$username:$password"),
    ],
  ]);
  
  return json_decode($response->getBody(), TRUE);
}
```

## JavaScript Examples

### Fetch API

```javascript
/**
 * Fetch media assets from DAM Open
 */
async function fetchDamAssets(baseUrl, username, password) {
  const endpoint = `${baseUrl}/jsonapi/media/image`;
  const response = await fetch(endpoint, {
    headers: {
      'Accept': 'application/vnd.api+json',
      'Authorization': 'Basic ' + btoa(`${username}:${password}`)
    }
  });
  
  return await response.json();
}

/**
 * Display an image from DAM Open
 */
function displayDamImage(imageUri, style = 'medium', baseUrl, username, password) {
  const imageUrl = `${baseUrl}/image-style/${style}/private/${imageUri}`;
  
  // Create image element with authenticated URL
  const img = document.createElement('img');
  img.src = imageUrl;
  img.alt = 'DAM Image';
  
  // Add authentication headers when the image is requested
  img.crossOrigin = 'use-credentials';
  
  return img;
}
```

### Axios Library

```javascript
// Using Axios library
import axios from 'axios';

/**
 * Fetch media assets using Axios
 */
async function fetchDamAssetsAxios(baseUrl, username, password) {
  const endpoint = `${baseUrl}/jsonapi/media/image`;
  
  const response = await axios.get(endpoint, {
    headers: {
      'Accept': 'application/vnd.api+json',
      'Authorization': 'Basic ' + btoa(`${username}:${password}`)
    }
  });
  
  return response.data;
}
```

## Drupal Module Integration

### Custom Module Structure

```
my_dam_integration/
  ├── my_dam_integration.info.yml
  ├── my_dam_integration.module
  ├── my_dam_integration.services.yml
  └── src/
      ├── DamOpenClient.php
      ├── Form/
      │   └── DamOpenSettingsForm.php
      └── Plugin/
          └── Field/
              └── DamOpenMediaField.php
```

### Service Configuration

```yaml
# my_dam_integration.services.yml
services:
  my_dam_integration.client:
    class: Drupal\my_dam_integration\DamOpenClient
    arguments:
      - '@config.factory'
      - '@http_client'
```

### Client Implementation

```php
<?php

namespace Drupal\my_dam_integration;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Client for DAM Open API.
 */
class DamOpenClient {

  /**
   * HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    ClientInterface $httpClient
  ) {
    $this->configFactory = $configFactory;
    $this->httpClient = $httpClient;
  }

  /**
   * Get media assets from DAM Open.
   */
  public function getMediaAssets() {
    $config = $this->configFactory->get('my_dam_integration.settings');
    $baseUrl = $config->get('base_url');
    $username = $config->get('username');
    $password = $config->get('password');
    
    $response = $this->httpClient->request('GET', $baseUrl . '/jsonapi/media/image', [
      'headers' => [
        'Accept' => 'application/vnd.api+json',
        'Authorization' => 'Basic ' . base64_encode("$username:$password"),
      ],
    ]);
    
    return json_decode($response->getBody(), TRUE);
  }

  /**
   * Get image URL with style.
   */
  public function getImageUrl($imageUri, $style = 'medium') {
    $config = $this->configFactory->get('my_dam_integration.settings');
    $baseUrl = $config->get('base_url');
    
    return $baseUrl . '/image-style/' . $style . '/private/' . $imageUri;
  }
}
```
