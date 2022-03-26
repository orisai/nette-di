# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/orisai/nette-di/compare/1.0.6...HEAD)

### Fixed

- `DefinitionsLoader`
    - `schema()` - more accurate return type
    - `loadDefinitionFromConfig()` returns `Definition` for `@reference::method` instead of `Reference`

## [1.0.6](https://github.com/orisai/nette-di/compare/1.0.5...1.0.6) - 2022-01-24

### Added

- All arrays have a key type defined

### Fixed

- `Environment`: `loadEnvParameters()` return type

## [1.0.5](https://github.com/orisai/nette-di/compare/1.0.4...1.0.5) - 2022-01-16

### Added

- Stub file with `BaseConfigurator` parameters for IDE neon support

## [1.0.4](https://github.com/orisai/nette-di/compare/1.0.3...1.0.4) - 2021-11-23

### Fixed

- `DefinitionsLoader`
	- Service `@reference` in definition arguments

## [1.0.3](https://github.com/orisai/nette-di/compare/1.0.2...1.0.3) - 2021-11-21

### Added

- `BaseConfigurator`
	- `setForceReloadContainer()`

## [1.0.2](https://github.com/orisai/nette-di/compare/1.0.1...1.0.2) - 2021-11-06

### Added

- `Environment`
	- `isConsole()`

## [1.0.1](https://github.com/orisai/nette-di/compare/1.0.0...1.0.1) - 2021-08-28

### Changed

- `ServiceManager`
	- `$container` is protected

## [1.0.0](https://github.com/orisai/nette-di/releases/tag/1.0.0) - 2021-08-19

### Added

- Boot
	- `ManualConfigurator`
	- `Environment`
	- `CookieGetter`
- Definitions
	- `DefinitionsLoader`
- Services
	- `ServiceManager`
