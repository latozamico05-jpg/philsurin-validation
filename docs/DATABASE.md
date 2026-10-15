# PHILSURIN database

`database/schema.sql` adds the requested core model without dropping legacy `users`, `varieties`, `validations`, or `grading_records`. `database/seed.sql` seeds roles, the supplied 19 varieties, measurement definitions, and system configuration. Run both only after taking a backup.

## Relationship map

```text
users --< user_roles >-- roles
users --< user_locations >-- locations --< masterlist >-- varieties
masterlist --< validation_sessions --< validation_measurements
masterlist --< harvest_records
c45_models --< c45_training_runs --< c45_training_records >-- masterlist
c45_models --< c45_recommendations --< c45_recommendation_factors
users --< audit_logs
```

`masterlist` is the central field entity. Measurements remain raw by replicate (1–4); averages must be calculated in queries or services. The transactional tables are the source of truth; daily and monthly metrics are caches.

Important indexes include unique record IDs, unique location codes, unique validation `(masterlist_id, stage, validation_date)`, unique measurement `(validation_id, replicate_no, measurement_code)`, model version, status, location, and audit creation date.
