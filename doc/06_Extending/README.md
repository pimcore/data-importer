---
title: Extending
description: Extension points for custom strategies, event listeners and Studio integration.
---

# Extending

Beyond the standard Symfony extension mechanisms, the bundle offers three ways to change its behaviour:

- [Custom Strategies](./01_Custom_Strategies.md): add a data source, file format, operator, data target, resolver
  strategy or cleanup strategy of your own.
- [Events](./02_Events.md): hook into the import process without replacing any component.
- [Studio Integration](./03_Studio_Integration.md): how the configuration panel is built, and which interfaces are
  supported for driving imports from the outside.

Choose an event when you only need to observe or adjust an element on its way into Pimcore. Choose a custom strategy when
the importer needs a capability it does not have.
