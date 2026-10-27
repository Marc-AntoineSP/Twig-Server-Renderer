Implement M05: Blocks and layout composition.

Read AGENTS.md, agent-kit/STATUS.md and this milestone in
docs/styleguide/MILESTONES.md. Inspect the repository and verify prerequisites.
Read the relevant architecture, contracts, security and acceptance sections.

Work: Implement the bounded composition tree, node selection/reordering/nesting and per-reference props. Reference immutable releases and resolve nested dependencies. Compile safe Twig from the graph; distinguish composition/source ownership. Support component -> block -> layout authoring and an explicit duplicate-and-convert to source action.

Required evidence: Compose several instances with distinct props, nest a block in a layout, reorder/remove nodes and persist. Preview/export preserves order and appearance. Detect cycles, excessive depth/nodes, incompatible duplicate versions and unknown references. Literal text containing Twig delimiters remains literal. Controls work with a keyboard.

Complete the implementation and meaningful checks, then update STATUS.md with
exact commands/results and remaining gaps. Do not mark unrun checks passed.
Preserve unrelated work; do not publish/deploy or proceed to the next milestone
unless requested. If something is blocked, continue independent work and report
the concrete blocker and the next useful command.
