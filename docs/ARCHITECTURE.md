**docs/API.md**
```md
# API
- GET `/api/my_animals` → Liste; Filter: `?habitat=forest`
- GET `/api/my_animals/{id}` → Detail (Custom JSON)
- GET `/api/my_animals/{id}/hal` → HAL JSON (Header: `Accept: application/hal+json`)
- GET `/api/my_animals/stats` → (optional) kleine Statistik

Beispiele:
```bash
curl -s http://my-zoo.ddev.site/api/my_animals | jq
curl -s "http://my-zoo.ddev.site/api/my_animals?habitat=forest" | jq
curl -s http://my-zoo.ddev.site/api/my_animals/1 | jq
curl -s -H "Accept: application/hal+json" http://my-zoo.ddev.site/api/my_animals/1/hal | jq
curl -s http://my-zoo.ddev.site/api/my_animals/stats | jq