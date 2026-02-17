# Laravel Fortify

- Fortify is a headless authentication backend that provides authentication routes and controllers for Laravel applications.
- This fork uses Doctrine ORM instead of Eloquent. Entities use public properties (camelCase, introduced Doctrine ORM 3.4.0) and EntityManager for persistence. There are no migrations to publish — the schema is derived from entity attributes.
- IMPORTANT: Always use the `search-docs` tool for detailed Laravel Fortify patterns and documentation.
- IMPORTANT: Activate `developing-with-fortify` skill when working with Fortify authentication features.
