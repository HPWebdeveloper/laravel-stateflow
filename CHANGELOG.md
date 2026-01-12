# Changelog

All notable changes to `laravel-stateflow` will be documented in this file.

## 1.0.0 - 2026-01-14

### Initial Release

Laravel StateFlow is inspired by similar concepts found in [Spatie Laravel Model States](https://github.com/spatie/laravel-model-states).
It combines the [state pattern](https://en.wikipedia.org/wiki/State_pattern) with [state machines](https://www.youtube.com/watch?v=N12L5D78MAA) to deliver enterprise-ready features: automatic state class discovery, automatic transition discovery, permissions, UI metadata, history tracking, and API resources.

#### Key Innovation

**Centralized State Topology** - Laravel StateFlow maintains a single, unified topology of all possible states and transitions for complex Laravel systems. This centralized architecture ensures that state definitions remain synchronized across your entire application, eliminating inconsistencies between backend logic, database enums, and frontend representations.

#### Core Features

- **State Pattern Implementation** - Clean, object-oriented state classes for Laravel Eloquent models
- **Automatic State Discovery** - Scans state directories to automatically discover all available states
- **Automatic Transition Discovery** - Analyzes `canTransitionTo()` methods to build the complete state graph
- **Backing Enum Generation** - `stateflow:sync-enum` command generates type-safe enums from state classes
- **State Class Scaffolding** - `make:state` command with full namespace support and custom transitions
- **Transition Permissions** - Built-in permission checking with `canTransitionTo()` integration
- **State History Tracking** - Complete audit trail of all state changes with context preservation
- **UI Metadata Support** - Rich metadata (labels, descriptions, colors, icons) for frontend rendering
- **API Resources** - Dedicated resources for serializing state information to JSON APIs
- **Query Scopes** - Eloquent scopes for filtering models by state (`whereState()`, `whereNotState()`)
- **Validation Rules** - Laravel validation rules for ensuring valid state transitions
- **State Change Events** - Event system for reacting to state transitions
- **Context Preservation** - Custom transition logic preserved during enum synchronization
- **Comprehensive Test Coverage** - 761 tests ensuring reliability and stability

#### Technical Requirements

- PHP ^8.3
- Laravel ^12.0

#### Commands

- `php artisan make:state {name}` - Create a new state class
- `php artisan stateflow:sync-enum {baseStateClass}` - Sync states to backing enum
