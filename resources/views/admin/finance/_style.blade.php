<style>
    .fin-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
    .fin-tabs a { padding: 8px 16px; border-radius: 10px; background: #fff; border: 1px solid #e6e8ef; color: #3e4853; font-weight: 600; font-size: 14px; }
    .fin-tabs a.active { background: #1f6f5c; border-color: #1f6f5c; color: #fff; }
    .fin-kpi { background: #fff; border: 1px solid #eef0f4; border-radius: 14px; padding: 16px 18px; height: 100%; }
    .fin-kpi .lbl { color: #7a8494; font-size: 13px; margin-bottom: 6px; }
    .fin-kpi .val { font-size: 24px; font-weight: 700; color: #1d2630; direction: ltr; text-align: right; }
    .fin-kpi .val small { font-size: 13px; color: #7a8494; font-weight: 500; }
    .fin-kpi .sub { font-size: 12px; color: #7a8494; margin-top: 4px; }
    .fin-kpi.good .val { color: #1f7a4d; }
    .fin-kpi.bad .val { color: #c0392b; }
    .fin-kpi.accent { background: #1f6f5c; border-color: #1f6f5c; }
    .fin-kpi.accent .lbl, .fin-kpi.accent .sub, .fin-kpi.accent .val small { color: #cfe7df; }
    .fin-kpi.accent .val { color: #fff; }
    .fin-flow { width: 100%; border-collapse: collapse; }
    .fin-flow td { padding: 9px 4px; border-bottom: 1px dashed #e6e8ef; font-size: 15px; }
    .fin-flow td.n { text-align: left; direction: ltr; font-weight: 600; white-space: nowrap; }
    .fin-flow tr.total td { border-bottom: 0; border-top: 2px solid #1d2630; font-weight: 700; font-size: 17px; }
    .fin-flow .minus { color: #c0392b; }
    .fin-flow .plus { color: #1f7a4d; }
    .fin-step { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #e8f3ef; color: #1f6f5c; font-weight: 700; font-size: 13px; margin-left: 8px; }
    .fin-card-title { font-size: 16px; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; }
    .fin-hint { background: #f6f8fb; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #5b6573; }
    .fin-big-input { font-size: 20px !important; font-weight: 700; height: 52px; }
    .fin-result { border-radius: 12px; padding: 14px 16px; background: #f6f8fb; }
    .fin-result .row-line { display: flex; justify-content: space-between; padding: 4px 0; font-size: 15px; }
    .fin-result .row-line b { direction: ltr; }
    .fin-result .row-line.big { font-size: 19px; font-weight: 700; border-top: 1px solid #dde2ea; margin-top: 6px; padding-top: 10px; }
    .fin-table td, .fin-table th { vertical-align: middle; white-space: nowrap; }
    .fin-num { direction: ltr; text-align: right; font-weight: 600; }
    .wa-preview { white-space: pre-wrap; background: #e7fbe6; border-radius: 12px; padding: 14px; font-size: 14px; line-height: 1.7; border: 1px solid #c9eec7; max-height: 420px; overflow: auto; }
    @media (max-width: 576px) { .fin-kpi .val { font-size: 20px; } }
</style>
