# Enforcing Module boundaries in CI (PHP and TypeScript)

Research for [#222](https://github.com/Vatsim-Scandinavia/events/issues/222), part of map [#220](https://github.com/Vatsim-Scandinavia/events/issues/220). Researched 2026-11-14.

**Question.** What are the options for failing CI when code reaches past a Module's front door, on the PHP side (PHPUnit 12, no Pest) and on the TypeScript/React side, and what does each cost?

"Front door" is used as in `GLOSSARY.md` → *Building the Hub*: the rest of the Hub reaches a Module only through its front door. Every tool below needs a way to tell a Module's front-door classes or files from its internals, so each section says how it does that.

## What the repo has today

Checked in the working tree, not from memory:

- PHP 8.5.4, `laravel/framework ^13.17`, `phpunit/phpunit ^12.5.12`. No PHPStan, Larastan, deptrac or phpat in `composer.json`.
- `nikic/php-parser` 5.9 is already installed, but only **transitively** (through `dedoc/scramble`, `psy/psysh` and PHPUnit's own coverage packages; `composer why nikic/php-parser`). Code that uses it directly would rely on a dependency the project hasn't declared.
- The frontend is linted with **oxlint 1.88** (`npm run lint` → `oxlint resources/js`, config in `.oxlintrc.json`). **ESLint isn't installed.** TypeScript is **7.0.2**, the native compiler: `require("typescript")` loads, but it has no JS compiler API (`createSourceFile` is undefined).
- Imports go through the `@/*` alias (`tsconfig.json` `paths`). Only 6 relative imports exist in `resources/js`.
- Inertia resolves pages by name from `resources/js/pages` (`app.tsx`, `pages: { path: './pages' }`). That lookup is a string, so no import-graph tool can see it.
- CI (`.github/workflows/ci.yml`, job `checks`) already runs `pint --test`, `npm run typecheck`, `npm run lint` and `php artisan test`. Any check that is a PHPUnit test or an oxlint rule runs in CI with no workflow change.

## What a static tool can and can't see in a Laravel app

This holds for every PHP option below, because they all read source code rather than run it.

- **Visible:** `use` imports, type hints, `new X`, `X::method()`, `X::class` (so `app(X::class)`, `$this->hasMany(X::class)` and `Event::listen(X::class, …)` all count as depending on `X`), `extends`, `implements`, traits, `instanceof`, `catch`, and attributes. deptrac's extractors handle each of these node types ([`ClassConstantExtractor`](https://github.com/deptrac/deptrac/blob/main/src/Core/Ast/Parser/Extractors/ClassConstantExtractor.php), [`StaticExtractor`](https://github.com/deptrac/deptrac/blob/main/src/Core/Ast/Parser/Extractors/StaticExtractor.php), [`KeywordExtractor`](https://github.com/deptrac/deptrac/blob/main/src/Core/Ast/Parser/Extractors/KeywordExtractor.php)). phpat has one PHPStan rule per kind ([`src/Rule/Assertion/Relation/Depend/`](https://github.com/carlosas/phpat/tree/main/src/Rule/Assertion/Relation/Depend)).
- **Facades.** `use Illuminate\Support\Facades\Cache` is a dependency on the framework, which is fine and not a Module boundary. What matters is that a facade resolves its target from the container by a string key returned from `getFacadeAccessor()` ([Laravel 13 docs, How Facades Work](https://laravel.com/docs/13.x/facades#how-facades-work)). A custom facade over a Module's internal class therefore hides that dependency from every tool. **Real-time facades** are worse: `use Facades\App\Staffing\Roster;` builds a class under a `Facades\` prefix ([Real-Time Facades](https://laravel.com/docs/13.x/facades#real-time-facades)), so a namespace rule written for `App\Staffing\…` doesn't match it. Whichever tool is chosen, add one rule that forbids `Facades\App\…`, or one that maps it back onto `App\…`.
- **Container.** Constructor injection and `app(X::class)` are visible. String bindings (`app('roster')`), aliases and bindings picked from config aren't. A Module's service provider binding its own interface to its own implementation is fine, because both sides belong to the same Module.
- **Eloquent relations.** The relation *definition* is visible (`hasMany(ShiftBooking::class)`), so a model in one Module that relates to a model in another gets flagged. That flag is useful: it makes cross-Module relations a deliberate decision. *Using* a relation (`$event->bookings->first()->roster`) is a magic property, and no tool that only reads `use` statements sees it. deptrac and phpat do read `@property` PHPDoc tags (phpat has [`DocPropertyTagRule`](https://github.com/carlosas/phpat/blob/main/src/Rule/Assertion/Relation/Depend/DocPropertyTagRule.php); deptrac has its `AnnotationReferenceExtractor`), but this codebase doesn't write them. Strings in `with('bookings.roster')`, `whereHas('…')` and morph maps are invisible to everything.
- **Inertia pages.** `Inertia::render('Staffing/Planner')` names a page by a string, and no PHP or TypeScript tool links it to a file.

So the tools catch what a class *names*. They can't catch what it reaches through strings or magic. The written rules in `.ai/rules` still have to cover those.

## PHP options

### deptrac

- **What it is.** A standalone static analyser for layer rules. Install with `composer require --dev deptrac/deptrac`, run with `vendor/bin/deptrac analyse` ([docs](https://deptrac.github.io/deptrac/)). The package is `deptrac/deptrac`. `qossmic/deptrac` is the old name and was last released in 2024-11 ([Packagist](https://packagist.org/packages/qossmic/deptrac)).
- **How it expresses the front door.** Each Module is a *layer* built from collectors: `directory`, `classLike`, `classNameRegex`, `bool` (must / must_not), `attribute`, `layer` and more ([collectors](https://deptrac.github.io/deptrac/collectors/)). A *ruleset* lists which layers each layer may use, and a `+` prefix also allows whatever that layer allows ([configuration](https://deptrac.github.io/deptrac/configuration/)). Two features map almost exactly onto a front door:
  - Any collector can take `private: true`. Tokens collected that way "can be referenced only by other tokens in the same layer", and other layers referencing them is a violation *even when the ruleset allows the layer*. The docs show this exact pattern for a public API with a private implementation ([collectors → extra collector configuration](https://deptrac.github.io/deptrac/collectors/)).
  - A `@deptrac-internal` docblock tag, or a custom one set in `analyser.internal_tag`, marks single classes as internal to their layer ([configuration](https://deptrac.github.io/deptrac/configuration/)).

  With `bool` + `attribute` collectors, a `#[FrontDoor]` attribute or a `Contracts/` folder could decide what is public.
- **Laravel.** It sees everything in the "Visible" list above. It can't see facade accessors, string bindings or magic relation properties. A `composer` collector can group vendor code into layers ([collectors](https://deptrac.github.io/deptrac/collectors/)), and `ignore_uncovered_internal_classes` keeps PHP built-ins out of the noise.
- **CI.** One more step in the `checks` job. The `github-actions` formatter turns violations into PR annotations and "is enabled by default while running in a GitHub actions environment". `junit` and `codeclimate` formatters also exist. A `baseline` formatter writes `skip_violations`, so the tool could be adopted before the refactor backlog is done ([formatters](https://deptrac.github.io/deptrac/formatters/)).
- **Maintenance.** 4.7.2 was released 2026-09-15 (4.7.0 and 4.7.1 in July 2026). It requires `php ^8.2` and Symfony `^6.4 || ^7.4 || ^8.0`, which fits Laravel 13's Symfony 8.1. The repo has about 3.0k stars, MIT licence, last push 2026-09-20, not archived ([Packagist](https://packagist.org/packages/deptrac/deptrac), [GitHub](https://github.com/deptrac/deptrac)).
- **Dependency cost.** One direct dev dependency, but it is heavy: it pulls `phpstan/phpstan ^2.0`, `nikic/php-parser`, `jetbrains/phpstorm-stubs` and eight Symfony components (`require` on Packagist 4.7.2). Config goes in a new `deptrac.yaml`. Needs maintainer approval.

### phpat

- **What it is.** "A PHPStan extension … designed to verify architectural requirements" ([GitHub](https://github.com/carlosas/phpat)). Rules are methods returning `PHPat::rule()` in test classes, which you register in `phpstan.neon` under the `phpat.test` tag. They run when you run `vendor/bin/phpstan analyse` ([getting started](https://github.com/carlosas/phpat/blob/main/docs/getting-started.md)). They aren't PHPUnit tests.
- **How it expresses the front door.** Selectors `inNamespace`, `classname` (with regex), `withFilepath`, `appliesAttribute`, `implements`, `extends`, combined with `Not`, `AllOf` and `AnyOf` ([selectors](https://github.com/carlosas/phpat/blob/main/docs/documentation/selectors.md)). Assertions include `shouldNot()->dependOn()` and `canOnly()->dependOn()` ([assertions](https://github.com/carlosas/phpat/blob/main/docs/documentation/assertions.md)). A front-door rule reads: classes `Not(inNamespace('App\Modules\Staffing'))` `shouldNot()->dependOn()` classes `AllOf(inNamespace('App\Modules\Staffing'), Not(<front door selector>))`. A rule method can `yield` one rule per Module ([rules → dynamic rule sets](https://github.com/carlosas/phpat/blob/main/docs/documentation/rules.md)). `nonIgnorable()` stops a rule from being baselined away.
- **Laravel.** It sees the same node types as deptrac, plus PHPDoc tags (`@property`, `@method`, `@mixin`, …), which `ignore_doc_comments` can turn off ([configuration](https://github.com/carlosas/phpat/blob/main/docs/documentation/configuration.md)). It inherits PHPStan's view of the code, and PHPStan doesn't understand facades, magic properties or the container without **Larastan**.
- **CI.** Runs as part of a PHPStan step. The repo has no PHPStan today, so this means a new `phpstan.neon` and a new CI step. PHPStan also reports its own errors at whatever level is set. Whether those can be fully switched off, leaving only phpat's rules, wasn't confirmed from primary sources. Assume a PHPStan run on a Laravel app comes with Larastan and some ignores.
- **Maintenance.** 0.12.5 was released 2026-09-15, requires `php ^8.1` and `phpstan/phpstan ^2.1`. The repo has about 1.3k stars, MIT licence, last push 2026-11-02 ([Packagist](https://packagist.org/packages/phpat/phpat), [GitHub](https://github.com/carlosas/phpat)). It is still on 0.x. (Larastan 3.15.0, 2026-11-11, supports Laravel 13, [Packagist](https://packagist.org/packages/larastan/larastan).)
- **Dependency cost.** `phpat/phpat` plus `phpstan/phpstan`, and realistically `larastan/larastan`. That's two or three new dev dependencies and a new kind of check. Worth it only if the project wants PHPStan anyway.

### A plain PHPUnit test (no new dependency)

- **What it is.** A test in `tests/Unit` (or a new `tests/Architecture` suite) that walks `app/` and reads each file's class references. It maps each class to a Module by namespace (for example `App\Modules\{Module}\…`) and fails when a class in one Module names a class in another Module that isn't in its front door.
- **How to read references without a dependency.** PHP's own [`PhpToken::tokenize()`](https://www.php.net/manual/en/phptoken.tokenize.php) is built in. Collect `T_USE` imports, and also `T_NAME_QUALIFIED` / `T_NAME_FULLY_QUALIFIED` tokens, so inline `\App\Modules\Staffing\Roster::class` doesn't slip past. Resolve short names against the file's `use` list and namespace. Reading only `use` statements misses fully qualified inline names, same-namespace references and group-use edge cases. Collecting the name tokens closes most of that, but it is still a hand-made resolver. `nikic/php-parser`'s `NameResolver` would do it properly, but only if it were declared in `require-dev` (a dependency to approve) instead of borrowed transitively.
- **How it expresses the front door.** It's code, so the front door can be whatever is decided: a sub-namespace (`App\Modules\X\Internal\*` is private), a folder, an explicit list per Module, or an attribute read through reflection.
- **Laravel.** It sees exactly what it parses, so the same blind spots as above. It's also easy to add Laravel-specific checks no off-the-shelf tool has, such as forbidding `Facades\App\…` imports or `Inertia::render('<other Module>/…')` strings.
- **CI.** Already runs in `php artisan test --compact`, with no workflow change. Failures read like any other test failure, without PR annotations.
- **Maintenance.** The team owns it. It's likely to be roughly 100–150 lines, plus tests for its own edge cases if it grows. There's no baseline feature: known exceptions would be an allow-list in the test.
- **Dependency cost.** None.

A middle ground: [`ta-tikoma/phpunit-architecture-test`](https://github.com/ta-tikoma/phpunit-architecture-test) is the library Pest's `arch()` is built on (`pestphp/pest-plugin-arch` 5.0.0 requires it, [Packagist](https://packagist.org/packages/pestphp/pest-plugin-arch)). It works in plain PHPUnit 10–13 through an `ArchitectureAsserts` trait (`assertDoesNotDependOn($layerA, $layerB)`). 0.8.7 was released 2026-02-17, and the repo has about 110 stars. It's one dev dependency that brings `nikic/php-parser`, `symfony/finder` and `phpdocumentor/reflection-docblock`, and it has no front-door concept beyond excluding classes from a layer.

## TypeScript / React options

An important finding: **the project lints with oxlint, not ESLint.** Anything ESLint-only means adding ESLint, or loading the plugin through oxlint's JS plugins.

### `no-restricted-imports` (built into oxlint, no new dependency)

- **What it is.** oxlint implements ESLint's `no-restricted-imports` natively, since v0.15.0 ([oxlint rule](https://oxc.rs/docs/guide/usage/linter/rules/eslint/no-restricted-imports.html)). It supports `paths` and `patterns` with gitignore-style `group` or `regex`, `importNames` / `allowImportNames`, `importNamePattern`, `allowTypeImports` and `message`.
- **How it expresses the front door.** Add one `overrides` entry per Module in `.oxlintrc.json` (`files` globs → `rules`, [config](https://oxc.rs/docs/guide/usage/linter/config.html)). For files outside `resources/js/modules/staffing/**`, forbid `@/modules/staffing/*` except its front door. ESLint's documented negation does this: `"patterns": ["import2/*", "!import2/good"]`, with negations last ([ESLint rule](https://eslint.org/docs/latest/rules/no-restricted-imports)). For example, group `["@/modules/staffing/**", "!@/modules/staffing", "!@/modules/staffing/index"]`.
- **Limits.**
  - It matches the import **specifier as written** and has no module resolver. So `../../staffing/internal/x` gets past a rule written for `@/modules/staffing/**`. Pair it with a rule that cross-Module imports use the alias. oxlint has `import/no-relative-parent-imports` (since v1.43.0, [rules list](https://oxc.rs/docs/guide/usage/linter/rules.html)), and a pattern on `../*` would also work. The codebase has only 6 relative imports, so this is cheap.
  - oxlint's regex flavour is Rust's, without lookahead or lookbehind. Only string-literal dynamic imports are checked ([oxlint rule](https://oxc.rs/docs/guide/usage/linter/rules/eslint/no-restricted-imports.html)).
  - One override per Module is repetitive, so it grows linearly with Modules. oxlint has no native `import/no-restricted-paths` (its "zones" form) or `import/no-internal-modules` ([rules list](https://oxc.rs/docs/guide/usage/linter/rules.html)).
- **CI.** Already in `npm run lint`.
- **Maintenance.** It's part of oxlint (oxc: about 23k stars, released weekly; 1.93.0 on 2026-11-09, [npm](https://www.npmjs.com/package/oxlint)).
- **Dependency cost.** None.

### eslint-plugin-boundaries

- **What it is.** An ESLint plugin that sorts files into *elements* by path pattern (`boundaries/elements`) and checks dependencies between them with policies (`boundaries/dependencies`, default `disallow`). `boundaries/entry-point` used to express a front door (allow / disallow matched against the imported file's `fileInternalPath`, e.g. `index.ts`). It is now **deprecated** in favour of `boundaries/dependencies` with `fileInternalPath` or file-category selectors ([entry-point](https://github.com/javierbrea/eslint-plugin-boundaries/blob/master/packages/website/docs/rules/entry-point.mdx), [dependencies](https://github.com/javierbrea/eslint-plugin-boundaries/blob/master/packages/website/docs/rules/dependencies.md)). `no-private` is another option. One config covers every Module through captured pattern groups, with no per-Module repetition.
- **With oxlint.** The plugin's own docs have an [Oxlint integration guide](https://github.com/javierbrea/eslint-plugin-boundaries/blob/master/packages/website/docs/guides/oxlint-integration.mdx) and a [runnable example](https://github.com/javierbrea/eslint-plugin-boundaries/tree/master/examples/oxlint-integration). It says the plugin loads through `jsPlugins`, because it needs neither a custom parser nor type-aware linting. It also needs an `import/resolver` setting. Without one, unresolved `tsconfig` path aliases are "silently reclassified as external" and skipped, so `@/…` imports would never be checked. (Some third-party write-ups say the plugin can't run under oxlint at all. The maintainer's guide and example contradict that, so the primary source is trusted here.) Under oxlint, though, it is an alpha feature: oxlint's JS plugins are "currently in alpha" ([oxlint JS plugins](https://oxc.rs/docs/guide/usage/linter/js-plugins.html)).
- **CI.** Inside `npm run lint`. JS plugins run slower than native oxlint rules, and the resolver resolves each import itself, without oxlint's cache (integration guide).
- **Maintenance.** 7.4.3 was released 2026-11-12, MIT licence, about 1.0k stars, last push 2026-11-13 ([npm](https://www.npmjs.com/package/eslint-plugin-boundaries), [GitHub](https://github.com/javierbrea/eslint-plugin-boundaries)).
- **Dependency cost.** `eslint-plugin-boundaries`, plus a resolver (`eslint-import-resolver-typescript` 4.4.5, or `eslint-import-resolver-oxc`). Both declare a **non-optional peer dependency on `eslint`** (`eslint >=6.0.0` and `eslint: *` in their npm metadata), so npm 7+ also installs ESLint into `node_modules` even though it never runs. That's two direct dev dependencies, plus ESLint pulled in.

### dependency-cruiser

- **What it is.** A standalone CLI that builds the module graph and checks `forbidden` rules with `from` / `to` `path` / `pathNot` regexes. *Group matching* (`$1`) lets a single rule say "a folder may only reach its peers' X" ([rules reference](https://github.com/sverweij/dependency-cruiser/blob/main/doc/rules-reference.md)), e.g. `from: { path: "^resources/js/modules/([^/]+)/" }, to: { path: "^resources/js/modules/", pathNot: ["^resources/js/modules/$1/", "^resources/js/modules/[^/]+/index\\.ts$"] }`. It resolves imports through `tsConfig` (aliases included). It can also check things lint rules can't, such as `reachable` and transitive dependencies.
- **TypeScript 7 is a problem.** Its `supportedTranspilers` lists `"typescript": ">=2.0.0 <7.0.0"`, and its `tsc` parser needs the `typescript` package's JS compiler API installed next to it ([package.json](https://github.com/sverweij/dependency-cruiser/blob/main/package.json), [options → parser](https://github.com/sverweij/dependency-cruiser/blob/main/doc/options-reference.md#parser)). This repo's TypeScript 7.0.2 has no such API. Without it, dependency-cruiser "will fall back to the JavaScript one", which "will still find all direct dependencies", but TypeScript-only constructs such as type-only imports may not show up ([FAQ](https://github.com/sverweij/dependency-cruiser/blob/main/doc/faq.md)). Getting full fidelity would mean installing a TypeScript 6 compiler alongside TypeScript 7.
- **CI.** A new `npx depcruise resources/js` step (or an npm script), plus a `.dependency-cruiser.cjs` config. Text and other reporters are available.
- **Maintenance.** It is very active: 18.7.2 was released 2026-11-02, about 7.4k stars, MIT licence ([npm](https://www.npmjs.com/package/dependency-cruiser)). It requires Node `^22 || ^24 || >=26`.
- **Dependency cost.** One dev dependency with about 18 runtime dependencies (acorn family, `enhanced-resolve`, `tsconfig-paths-webpack-plugin`, …), plus possibly a second TypeScript for the `tsc` parser.

## Comparison

| | Front door | Facades / container / Eloquent | CI | Health | New dependency |
|---|---|---|---|---|---|
| **deptrac** | Best fit: `private: true` collectors, `@deptrac-internal` | Sees `::class`, type hints, static calls, PHPDoc; not string keys or magic properties | New step; GitHub annotations by default; baseline | Active (4.7.2, Sept 2026) | Yes: 1 direct, heavy tree (PHPStan, Symfony) |
| **phpat** | `Not` / `AllOf` selectors, `canOnly()->dependOn()` | Same, through PHPStan; needs Larastan for Laravel magic | New PHPStan step and config | Active, 0.x | Yes: phpat + PHPStan (+ Larastan) |
| **PHPUnit test** (`PhpToken`) | Whatever the code says | What it parses; easy to add Laravel-specific checks (real-time facades, `Inertia::render` strings) | Already runs in `php artisan test` | Team-owned | None |
| **oxlint `no-restricted-imports`** | Per-Module `overrides` with `!` negation | n/a (frontend) | Already runs in `npm run lint` | Part of oxlint | None |
| **eslint-plugin-boundaries** | `dependencies` + `fileInternalPath`; one config for all Modules | n/a | In `npm run lint` via alpha `jsPlugins` | Active (7.4.3, Nov 2026) | Yes: plugin + resolver (+ ESLint as a peer) |
| **dependency-cruiser** | `pathNot` with `$1` group matching | n/a | New step | Very active | Yes: 1 + many sub-deps; TypeScript 7 not supported by its `tsc` parser |

## Recommendation

*This is a recommendation, not a decision. Any new dependency still needs the maintainer's approval.*

**Start with the two zero-dependency options, and keep deptrac as the upgrade if the rules outgrow them.**

- **PHP:** a plain PHPUnit architecture test using `PhpToken`, with one front-door convention (for example: only classes directly under `App\Modules\{X}\` are public, anything deeper is private), plus checks for `Facades\App\…` imports and cross-Module `Inertia::render` page names. It runs in today's CI, adds nothing to `composer.json`, and catches what the off-the-shelf tools catch (`use`, `::class`, type hints, `new`, static calls). If the rules grow beyond a simple "front door or not" (layers inside a Module, a baseline during the refactor backlog, PR annotations), **deptrac** is the tool to propose. Its `private: true` collectors express the front door directly, and it doesn't require running PHPStan. phpat is only worth it if the project decides to adopt PHPStan or Larastan for its own sake.
- **TypeScript:** oxlint's built-in **`no-restricted-imports`**, with one `overrides` entry per Module and a rule that cross-Module imports go through the `@/` alias (`import/no-relative-parent-imports` or a `../` pattern), since the rule matches specifiers, not resolved paths. If the per-Module repetition becomes a burden, **eslint-plugin-boundaries** through oxlint `jsPlugins` is the next step, keeping in mind that it's alpha and pulls in ESLint as a peer. dependency-cruiser is the least good fit right now, because its TypeScript parser doesn't support TypeScript 7.
- **Either way:** strings (container keys, `with('…')`, morph maps, Inertia page names) and magic relation properties are invisible to all of these tools. The `.ai/rules` text has to state the front-door rule for those too, rather than relying on CI alone.
