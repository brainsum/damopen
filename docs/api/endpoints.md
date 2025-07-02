# API Endpoints

## Media Assets

### Get Media Assets

```
GET /jsonapi/media/image
```

Retrieves a list of media assets of type "image".

**Parameters:**
- `filter[field_name][value]`: Filter by field value
- `page[limit]`: Number of items per page
- `page[offset]`: Offset for pagination
- `sort`: Field to sort by (e.g., `sort=created`)

**Response:**
```json
{
  "data": [
    {
      "type": "media--image",
      "id": "uuid",
      "attributes": {
        "name": "Image Name",
        "created": "2023-01-01T12:00:00+00:00",
        "changed": "2023-01-01T12:00:00+00:00",
        "thumbnail": {
          "alt": "Alt text",
          "title": "Title text",
          "url": "https://example.com/sites/default/files/styles/thumbnail/private/image.jpg"
        },
        "assets": {
          "medium": "https://example.com/sites/default/files/styles/medium/private/image.jpg",
          "large": "https://example.com/sites/default/files/styles/large/private/image.jpg"
        },
        "field_category": ["Category Name"],
        "field_keywords": ["Keyword1", "Keyword2"],
        "field_gps_gpslatitude": "47.497912",
        "field_gps_gpslongitude": "19.040235",
        "field_iptc_by_line": "Photographer Name",
        "field_iptc_caption": "Image Caption",
        "field_iptc_object_name": "Object Name"
      }
    }
  ]
}
```

### Get Single Media Asset

```
GET /jsonapi/media/image/{uuid}
```

Retrieves a single media asset by its UUID.

**Response:**
Same structure as above but with a single item.

## File Access

### Get File

```
GET /system/files/{file_path}
```

Retrieves a file by its path.

### Get Private File

```
GET /system/private/file/download/{file_path}
```

Retrieves a private file by its path. Requires authentication.

### Get Image Style

```
GET /image-style/{style}/private/{file_path}
```

Retrieves an image processed with the specified image style.

**Parameters:**
- `style`: The image style to apply (e.g., `thumbnail`, `medium`, `large`)
- `file_path`: Path to the file

## Taxonomy

### Get Categories

```
GET /jsonapi/taxonomy_term/category
```

Retrieves a list of categories.

### Get Keywords

```
GET /jsonapi/taxonomy_term/keywords
```

Retrieves a list of keywords.

## Media Collections

### Get Collections

```
GET /jsonapi/media_collection/media_collection
```

Retrieves a list of media collections.

### Get Collection Items

```
GET /jsonapi/media_collection_item/media_collection_item
```

Retrieves a list of media collection items.
