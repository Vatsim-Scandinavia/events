# Queries live on Eloquent, in custom builders for Event and Occurrence, with no repositories

Queries are written with Eloquent, as Laravel's docs show. Repositories would wrap each model, so every query would be written twice and eager loading, scopes and pagination would be lost at the wrapper. We don't need what they usually offer: we won't swap the database, and tests run against a real database with factories. Most models keep their queries as `#[Scope]` methods. `Event` and `EventOccurrence` each get a custom builder instead (`App\Models\Builders\EventBuilder` and `OccurrenceBuilder`, attached with `#[UseEloquentBuilder]`), because they carry many queries shared by the web pages, the API and the console, and their model files already hold relations, scheduling and lifecycle behaviour. Any other model moves to a builder once its queries crowd the model.

## Consequences

- Form requests, including the API's filter request, hand over validated values. They never take or return a query builder.
- A query is written once, on the builder, and used by every caller, whether that's the web listing, the API or the console.
