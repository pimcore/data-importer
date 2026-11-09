# Core's change control contract, for the tests

A verbatim copy of `lib/Contracts/ChangeControl` of pimcore/pimcore at `68785d6f184d8b35b6cad2f187713810df9b035a`, where pimcore/pimcore#19683 merged; only the comment on `MergeProblemCode::InvalidPlace` is wrapped, to the bundle's line length. No released core ships the contract yet, so `tests/_bootstrap.php` and `phpstan.neon` load this copy where core has none.

Once a core release ships it, delete this directory and those two entries, and raise this bundle's `pimcore/pimcore` constraint to the release that ships the contract.
