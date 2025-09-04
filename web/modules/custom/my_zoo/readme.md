# My Zoo (Modul) – Drupal 11 + HAL

Stellt Tierdaten als **JSON** bereit und zusätzlich **HAL-JSON** für einzelne Datensätze.

## Inhalte & Konfiguration
- Inhaltstyp: `my_animals` („Meine Tiere“)
- Felder: `field_scientific_name`, `field_photo`, `field_habitat`, `field_gender` (`male|female|unknown`), `field_age`, `field_birth_date`
- Taxonomie: Vocabulary `habitat` („Lebensraum“)
- Konfiguration liegt unter `config/install/` (Import beim Modul-Install). Install-Hook legt Beispiel-Habitate an.

## Endpunkte
- `GET /api/my_animals` — alle Tiere, optional `?habitat=forest`
- `GET /api/my_animals/{id}` — Detail (Custom JSON)
- `GET /api/my_animals/{id}/hal` — Detail als **HAL** (`application/hal+json`)
- *(optional)* `GET /api/my_animals/stats` — einfache Statistik

## Beispielantwort (gekürzt)
```json
{ "items": [{ "id": 1, "title": "Löwe", "scientific_name": "Panthera leo",
"foto": "https://my-zoo.ddev.site/…/loewe.jpg", "habitat": ["grasslands"], "gender": "male",
"age": 5, "birth_date": "2020-04-23T18:25:43+00:00" }] }
```

### (Optional) Statistik
`GET /api/my_animals/stats` — Liefert `total`, `by_habitat` (Map) und `avg_age`.

