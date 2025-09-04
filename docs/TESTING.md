
**docs/ARCHITECTURE.md**
```md
# Architektur
- Modul: `my_zoo` (Drupal 11)
- Content-Type `my_animals` + Felder, Vokabular `habitat` via `config/install`
- Controller `AnimalController` (Routen in `my_zoo.routing.yml`)
- Service `MyZooService` (IDs laden, Mapping, Stats)
- Caching: `CacheableJsonResponse`/`CacheableResponse`, Tags (`node:*`, `taxonomy_term_list`), Contexts (`url.query_args:habitat`)
- HAL: `serializer->serialize($node, 'hal_json')` + Header `application/hal+json`
- Optional: `/api/my_animals/stats` (Total, je Habitat, Durchschnittsalter)
