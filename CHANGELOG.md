# Changelog – Bombora Read More Toggle

## [1.1.1] – 2026-11-23
- Opraveno: pojistka proti dvojímu načtení v 1.1.0 plugin vypnula hned při načtení (PHP deklaruje třídu dřív, než proběhne kontrola), takže tag [read] se nezpracoval. Třída se teď jmenuje `Bombora_Read_More_Toggle`.

## [1.1.0] – 2026-11-23
- Modul **Bombora Manageru** (repo `bombora-tech/bombora-read-more-toogle`), složka `bombora-read-more-toggle`, název „Bombora Read More Toggle“.
- Vestavěné překlady tlačítek „Pokračovat“ / „Skrýt“: SK, DE, FR, NL, EN, RU (CS je výchozí). Překlad z WPML String Translation má dál přednost.
- Tlačítko má `role="button"` a `aria-controls`; pojistka proti dvojímu načtení.
- Tag `[read]…[/read]`, CSS třídy i WPML řetězce beze změny – existující obsah funguje dál.

## [1.0.0]
- Původní verze (složka `read-more-toggle`).
