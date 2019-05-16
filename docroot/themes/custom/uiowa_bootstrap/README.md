## Guidelines
- **Twig Component Templates** should be generic and not make style assumptions. Avoid utility and stylized classes. See style variations for more information.
- **Style variations** should implement a BEM style class `card--variation` at the top level of the component and leverages css to target and control styles. For example `.card--variation > .card-header: @extend text-muted;`.

