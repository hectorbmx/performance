# Client Progress Phase 2 Roadmap

## Context

The coach client edit screen now has a `Progreso` tab with summary cards, body weight history, and metric history. Phase 2 should turn that view from a readable history into a practical coaching dashboard.

## Proposed Scope

1. Add date and metric filters.
   - Quick ranges: 7, 30, 90 days, all time.
   - Metric selector for focused review.
   - Keep the default state useful without extra clicks.

2. Add charts.
   - Body weight line chart.
   - Metric trend chart for the selected metric.
   - Show enough historical context without replacing the tables.

3. Add athlete goals.
   - Weight goal.
   - Metric-specific goals.
   - Goal status: in progress, reached, delayed.
   - Requires a small data model design before implementation.

4. Add coach follow-up notes.
   - General notes by date for the athlete.
   - Separate from per-record metric notes.
   - Useful for observations, adherence, energy, and plan adjustments.

5. Add simple attention alerts.
   - No recent body weight.
   - Key metric not updated.
   - Sudden weight change.
   - Keep alerts informational first, not blocking.

## Recommended First Step

Start with filters and charts because the current data already supports them. Add goals after the coach workflow is clearer, since goals need database structure and lifecycle decisions.
