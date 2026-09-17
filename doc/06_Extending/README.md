---
title: Extending
description: Extension points for custom strategies and event listeners.
---

# Extending

Beyond the standard Symfony extension mechanisms, the bundle offers two ways to change its behaviour:

- [Custom Strategies](./01_Custom_Strategies.md): add a data source, file format, operator, data target, resolver
  strategy or cleanup strategy of your own.
- [Events](./02_Events.md): hook into the import process without replacing any component.

Choose an event when you only need to observe or adjust an element on its way into Pimcore. Choose a custom strategy when
the importer needs a capability it does not have.
