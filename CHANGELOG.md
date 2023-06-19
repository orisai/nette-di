# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/orisai/nette-di/compare/1.3.2...HEAD)

## [1.3.2](https://github.com/orisai/nette-di/compare/1.3.1...1.3.2) - 2023-06-20

### Fixed

- `Environment`
	- `loadEnvParameters()` - fix for case `$_SERVER` contains numeric key

## [1.3.1](https://github.com/orisai/nette-di/compare/1.3.0...1.3.1) - 2023-03-22

### Fixed

- `BaseConfigurator`
	- `unlink()` may fail when reloading Container multiple times in a same process (in tests)

## [1.3.0](https://github.com/orisai/nette-di/compare/1.2.3...1.3.0) - 2023-02-15

### Added

- `ConstantsExtension`
- `PhpExtension`

## [1.2.3](https://github.com/orisai/nette-di/compare/1.2.2...1.2.3) - 2022-12-14

### Changed

- `ServiceManager` - constructor is not final

## [1.2.2](https://github.com/orisai/nette-di/compare/1.2.1...1.2.2) - 2022-12-09

### Changed

- Composer
	- allows PHP 8.2

## [1.2.1](https://github.com/orisai/nette-di/compare/1.2.0...1.2.1) - 2022-11-29

### Added

- `BaseConfigurator`
  - `createContainer()` allows to not call `initialize()` on container

## [1.2.0](https://github.com/orisai/nette-di/compare/1.1.0...1.2.0) - 2022-10-02

### Added

- `CookieDebugSwitcher`
  - `isDebug()` (as an alias to `Environment::isCookieDebug($switcher)`)

## [1.1.0](https://github.com/orisai/nette-di/compare/1.0.8...1.1.0) - 2022-09-09

### Added

- Runtime cookie debug switch
  - `Environment::isCookieDebug()`
  - `DebugCookieStorage` interface
    - `FileDebugCookieStorage`
  - `CookieDebugSwitcher`

- `getenv()` as a fallback to `$_SERVER` in:
  - `Environment::isEnvDebug()`
  - `Environment::loadEnvParameters()`

### Changed

- `Environment`
  - `isEnvDebugMode()` is deprecated, use `isEnvDebug()` instead

## [1.0.8](https://github.com/orisai/nette-di/compare/1.0.7...1.0.8) - 2022-05-06

### Fixed

- Stub file
  - Add missing `container` parameter

## [1.0.7](https://github.com/orisai/nette-di/compare/1.0.6...1.0.7) - 2022-03-26

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
