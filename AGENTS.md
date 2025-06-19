# Contributor Guide

This guide documents the established patterns and conventions in the Podsumer codebase. These are not suggestions or improvements, but observations of how the repository currently operates. Contributors should follow these existing patterns to maintain consistency.

## Repository Overview

Podsumer is a self-hosted podcast aggregator (podcatcher) written in PHP with zero third-party runtime dependencies. The codebase prioritizes simplicity, privacy, and self-containment.

## Core Principles

1. **Zero Runtime Dependencies**: The application runs without any third-party PHP packages in production. Only PHPUnit is used as a development dependency.
2. **Single Namespace**: All code lives under `Brickner\Podsumer` with no sub-namespaces.
3. **SQLite Only**: The application uses SQLite exclusively for data storage, with support for both in-database and on-disk media storage.
4. **Privacy First**: No telemetry, analytics, or external service calls except to podcast feeds.

## Code Standards

### PHP Conventions

1. **Strict Types**: Every PHP file begins with `<?php declare(strict_types = 1);`
2. **Type Hints**: All method parameters and return types must be explicitly typed
3. **Property Visibility**: Use `protected` for class properties, not `private`
4. **Method Visibility**: Always explicitly declare method visibility (`public`, `protected`)
5. **Comments**: Use `#` for single-line comments, not `//`
6. **Imports**: Use `use` statements at the top for specific classes/functions from global namespace
7. **Short Tags**: Templates use `<?` and `<?=` instead of `<?php` and `<?php echo`

### Naming Conventions

1. **Classes**: PascalCase (e.g., `Main`, `State`, `FSState`)
2. **Methods**: camelCase (e.g., `getFeed`, `addFeedItems`)
3. **Properties**: snake_case (e.g., `$state_file_path`, `$test_mode`)
4. **Constants**: UPPERCASE with underscores (e.g., `VERSION`, `PODSUMER_PATH`)
5. **Database Tables**: Plural snake_case (e.g., `feeds`, `items`, `file_contents`)
6. **Database Columns**: snake_case (e.g., `url_hash`, `last_update`)
7. **Configuration Keys**: snake_case (e.g., `media_dir`, `state_file`)

### File Organization

1. **Source Code**: `src/Brickner/Podsumer/` - flat structure, no subdirectories
2. **Tests**: `tests/Brickner/Podsumer/` - mirrors source structure
3. **Templates**: `templates/` - uses `.html.php` and `.xml.php` extensions
4. **Configuration**: `conf/` - INI format configuration files
5. **SQL**: `sql/` - Database schema and migrations
6. **Web Root**: `www/` - Contains only `index.php` entry point

## Architecture Patterns

### Routing System

1. Routes are defined using PHP 8 attributes on global functions in `www/index.php`
2. Route format: `#[Route('/path', 'METHOD', $requiresAuth)]`
3. Route handlers are global functions that accept `array $args` parameter
4. Route discovery uses reflection to find functions with Route attributes
5. All route handlers return `void` and output directly

Example:
```php
#[Route('/feed', 'GET', true)]
function feed(array $args): void
{
    global $main;
    // Implementation
}
```

### Database Patterns

1. All queries use prepared statements via PDO
2. SQL strings are multi-line with specific formatting
3. Use `ON CONFLICT DO UPDATE SET id=id` for upsert operations
4. Foreign keys are always enabled with cascading deletes
5. Hash columns (md5) used for deduplication
6. PRAGMA settings optimize for performance over durability

### Error Handling

1. Exceptions are caught, logged, and only re-thrown in test mode
2. HTTP errors return appropriate status codes (404, 500)
3. Missing resources return 404 without throwing exceptions
4. Validation uses `empty()` checks with early returns

### Template System

1. Templates use PHP as the templating language
2. Variables are extracted into template scope
3. All template variables are sanitized with `strip_tags()`
4. Base template wraps content templates
5. Static render methods on Template class

## Testing Conventions

1. Test classes are declared as `final`
2. Test class names follow `{ClassName}Test` pattern
3. Test methods follow `test{MethodName}` pattern
4. Uses `expectNotToPerformAssertions()` for tests without assertions
5. Creates temporary test database that's cleaned up after tests
6. Real external URLs used in tests (e.g., NPR podcast feed)
7. Test mode flag passed through application stack

## Development Workflow

### Git Workflow

1. Default branch is `development`
2. Pull requests target `development` branch
3. GitHub Actions run on push and PR to `development`

### CI/CD Pipeline

1. Composer validation with `--strict` flag
2. PHPUnit tests with coverage reporting
3. Coverage badge generated and pushed to `image-data` branch
4. Composer dependencies cached for performance

### Docker Development

1. Based on `php:8.2.12-apache-bookworm` image
2. Application installed in `/opt/podsumer/`
3. Runs on port 3094
4. Uses `www-data` user with 755 permissions
5. Composer autoload regenerated at build time

## Configuration

1. INI format with sections `[section_name]`
2. Comments use `#` prefix
3. Typed value parsing enabled
4. Two-level access pattern: `section.key`
5. Runtime configuration changes supported
6. Test-specific configuration for test mode

## UI Conventions

1. Tailwind CSS loaded from CDN
2. Dark theme using neutral colors
3. Amber accent colors for links
4. Database size shown in GB
5. Footer includes version and contribution links

## Security Patterns

1. HTTP Basic Authentication when configured
2. Input sanitization using `filter_var_array()`
3. File paths validated before access
4. No direct SQL query building - always use prepared statements

## Code Quality Rules

1. No more than 3 attempts to fix linter errors per file
2. Methods should be focused and relatively short
3. Early returns preferred over nested conditionals
4. Guard clauses at method start
5. Explicit null checks rather than truthy/falsy checks

## Special Considerations

1. **Media Storage**: All media files are stored on disk in the configured media_dir
2. **Performance**: Database operations use transactions and optimized PRAGMA settings
3. **Compatibility**: Requires SQLite 3.6.19+ with foreign key support
4. **Memory**: No memory limit set (`memory_limit = -1`)

## File Patterns Not to Create

1. Never create documentation files unless explicitly requested
2. Never create README files unless explicitly requested  
3. Never create unnecessary helper or utility files
4. Always prefer editing existing files over creating new ones

## Testing Your Changes

1. Run tests: `composer test`
2. Generate coverage: `composer coverage`
3. View coverage locally: `composer coverage-dev`
4. Ensure no regression in test coverage

## Contribution Behavior

Follow the CODE-OF-CONDUCT.md: "Be kind. Don't be mean." Apply the Golden Rule in all interactions.

## License

All contributions must be compatible with the MIT License. Copyright is held by Joshua Brickner.