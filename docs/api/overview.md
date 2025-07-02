# DAM Open API Overview

## Introduction

DAM Open provides a RESTful API for integrating with Drupal-based systems. The API is built on top of Drupal's JSON:API and REST modules, allowing for standardized access to digital assets stored in the DAM system.

## Architecture

The DAM Open API follows REST principles and uses JSON:API specification for structured responses. It provides access to media assets, taxonomies, and file operations through standardized endpoints.

## Key Features

- **Media Asset Access**: Retrieve and manage digital assets
- **Taxonomy Integration**: Access categories and keywords
- **Image Style Processing**: Generate different image styles on demand
- **Authentication**: Secure access through Basic Authentication
- **Standardized Responses**: Consistent JSON:API formatted responses

## Prerequisites

To integrate with DAM Open, your Drupal installation needs:

- Drupal 9.1+ or Drupal 10.0+
- JSON:API module enabled
- REST module enabled
- Basic Auth module enabled

## Module Dependencies

The API functionality is provided by the following modules:
- `damopen_assets_api`: Core API functionality
- `damopen_common`: Common utilities and services
- `jsonapi_extras`: Enhanced JSON:API functionality

## Getting Started

1. Ensure you have proper authentication credentials
2. Explore the available endpoints
3. Test API calls using the examples provided in the [Integration Examples](integration-examples.md) document
