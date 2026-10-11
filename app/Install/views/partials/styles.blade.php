<style>
    .wx-install {
        --wx-radius: 14px;
        --wx-muted: var(--tblr-secondary);
        --wx-line: var(--tblr-border-color);
        --wx-surface: var(--tblr-bg-surface);
        --wx-soft: var(--tblr-bg-surface-secondary);
        --wx-success: var(--tblr-success);
        --wx-danger: var(--tblr-danger);
        min-height: 100vh;
        background: var(--tblr-body-bg);
    }

    [x-cloak] { display: none !important; }

    /* Shell */
    .wx-shell {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1rem;
    }
    .wx-card {
        width: 100%;
        max-width: 980px;
        display: grid;
        grid-template-columns: 250px 1fr;
        background: var(--wx-surface);
        border: 1px solid var(--wx-line);
        border-radius: var(--wx-radius);
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 12px 32px -12px rgba(16, 24, 40, .12);
        overflow: hidden;
    }

    /* Sidebar */
    .wx-aside {
        display: flex;
        flex-direction: column;
        gap: 2rem;
        padding: 1.75rem 1.5rem;
        background: var(--wx-soft);
        border-right: 1px solid var(--wx-line);
    }
    .wx-brand {
        display: flex;
        align-items: center;
        gap: .625rem;
    }
    .wx-brand img {
        width: 32px;
        height: 32px;
        border-radius: 8px;
    }
    .wx-brand-name {
        font-weight: 600;
        font-size: 1rem;
        line-height: 1.1;
    }
    .wx-brand-sub {
        font-size: .75rem;
        color: var(--wx-muted);
    }
    .wx-theme-toggle {
        margin-left: auto;
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 0;
        background: transparent;
        color: var(--wx-muted);
    }
    .wx-theme-toggle:hover { background: var(--wx-line); color: var(--tblr-body-color); }
    .wx-theme-toggle .ti { font-size: 1.125rem; }
    .wx-theme-toggle .ti-sun { display: none; }
    [data-bs-theme="dark"] .wx-theme-toggle .ti-sun { display: inline; }
    [data-bs-theme="dark"] .wx-theme-toggle .ti-moon { display: none; }

    .wx-steps {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: .25rem;
    }
    .wx-steps li {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .45rem .5rem;
        border-radius: 8px;
        color: var(--wx-muted);
        font-size: .875rem;
    }
    .wx-steps li.is-active {
        background: var(--wx-surface);
        color: var(--tblr-body-color);
        font-weight: 600;
        box-shadow: 0 0 0 1px var(--wx-line);
    }
    .wx-steps li.is-done { color: var(--tblr-body-color); }
    .wx-step-dot {
        flex: none;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .7rem;
        font-weight: 600;
        border: 1.5px solid var(--wx-line);
        background: var(--wx-surface);
    }
    .wx-steps li.is-active .wx-step-dot {
        border-color: var(--tblr-primary);
        background: var(--tblr-primary);
        color: #fff;
    }
    .wx-steps li.is-done .wx-step-dot {
        border-color: var(--wx-success);
        background: var(--wx-success);
        color: #fff;
    }

    .wx-aside-foot {
        margin-top: auto;
        font-size: .8125rem;
        display: flex;
        flex-direction: column;
        gap: .375rem;
    }
    .wx-aside-foot a {
        color: var(--wx-muted);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }
    .wx-aside-foot a:hover { color: var(--tblr-body-color); }
    .wx-version { color: var(--wx-muted); font-size: .75rem; margin-top: .5rem; }

    /* Main column */
    .wx-main {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .wx-progress { display: none; }
    .wx-head { padding: 2.25rem 2.5rem 0; }
    .wx-eyebrow {
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--tblr-primary);
        margin-bottom: .5rem;
    }
    .wx-head h1 {
        font-size: 1.5rem;
        font-weight: 650;
        margin: 0 0 .375rem;
        letter-spacing: -.01em;
    }
    .wx-head p {
        color: var(--wx-muted);
        margin: 0;
        max-width: 56ch;
    }
    .wx-body {
        padding: 1.75rem 2.5rem 2rem;
        flex: 1;
    }
    .wx-actions {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: 1rem 2.5rem;
        border-top: 1px solid var(--wx-line);
        background: var(--wx-surface);
    }
    .wx-actions .wx-primary { margin-left: auto; }
    .wx-actions .btn { min-height: 40px; padding-left: 1.1rem; padding-right: 1.1rem; }

    /* Building blocks */
    .wx-banner {
        display: flex;
        gap: .875rem;
        align-items: flex-start;
        padding: .875rem 1rem;
        border-radius: 10px;
        border: 1px solid var(--wx-line);
        background: var(--wx-soft);
        font-size: .875rem;
    }
    .wx-banner .ti { font-size: 1.25rem; line-height: 1.25rem; flex: none; }
    .wx-banner strong { display: block; font-weight: 600; }
    .wx-banner.is-success { border-color: rgba(var(--tblr-success-rgb), .35); background: rgba(var(--tblr-success-rgb), .07); }
    .wx-banner.is-success .ti { color: var(--wx-success); }
    .wx-banner.is-danger { border-color: rgba(var(--tblr-danger-rgb), .35); background: rgba(var(--tblr-danger-rgb), .07); }
    .wx-banner.is-danger .ti { color: var(--wx-danger); }
    .wx-banner.is-warning { border-color: rgba(var(--tblr-warning-rgb), .45); background: rgba(var(--tblr-warning-rgb), .08); }
    .wx-banner.is-warning .ti { color: var(--tblr-warning); }
    .wx-banner.is-info .ti { color: var(--tblr-primary); }
    .wx-banner-detail {
        margin-top: .25rem;
        color: var(--wx-muted);
        word-break: break-word;
    }

    .wx-section-title {
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--wx-muted);
        margin: 0 0 .625rem;
    }

    .wx-checks {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: .5rem;
    }
    .wx-check {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .5rem .75rem;
        border: 1px solid var(--wx-line);
        border-radius: 8px;
        font-size: .8125rem;
        min-width: 0;
    }
    .wx-check code {
        background: none;
        padding: 0;
        color: inherit;
        font-size: .8125rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .wx-check .ti { flex: none; font-size: 1rem; }
    .wx-check.is-pass .ti { color: var(--wx-success); }
    .wx-check.is-fail { border-color: rgba(var(--tblr-danger-rgb), .45); background: rgba(var(--tblr-danger-rgb), .06); }
    .wx-check.is-fail .ti { color: var(--wx-danger); }
    .wx-check.is-wide { grid-column: 1 / -1; max-width: 360px; }
    .wx-check small { color: var(--wx-muted); margin-left: auto; white-space: nowrap; }

    .wx-code {
        position: relative;
        margin-top: .5rem;
        padding: .75rem 2.75rem .75rem .875rem;
        border-radius: 8px;
        background: #0f172a;
        color: #e2e8f0;
        font-family: var(--tblr-font-monospace);
        font-size: .8125rem;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-all;
    }
    [data-bs-theme="dark"] .wx-code { background: #0b1120; border: 1px solid var(--wx-line); }
    .wx-copy {
        position: absolute;
        top: .4rem;
        right: .4rem;
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 6px;
        background: rgba(255, 255, 255, .08);
        color: #cbd5e1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .wx-copy:hover { background: rgba(255, 255, 255, .16); color: #fff; }

    .wx-link-btn {
        border: 0;
        background: none;
        padding: 0;
        color: var(--tblr-primary);
        font-size: .875rem;
        display: inline-flex;
        align-items: center;
        gap: .25rem;
    }

    .wx-eula {
        max-height: 340px;
        overflow-y: auto;
        padding: 1.25rem 1.5rem;
        border: 1px solid var(--wx-line);
        border-radius: 10px;
        background: var(--wx-soft);
        font-size: .875rem;
    }
    .wx-eula h1 { font-size: 1.125rem; }
    .wx-eula h2 { font-size: 1rem; margin-top: 1.25rem; }
    .wx-eula h3 { font-size: .9375rem; }

    .wx-accept {
        display: flex;
        align-items: center;
        gap: .625rem;
        margin-top: 1rem;
        padding: .75rem 1rem;
        border: 1px solid var(--wx-line);
        border-radius: 10px;
        cursor: pointer;
        font-size: .875rem;
    }
    .wx-accept .form-check-input { margin: 0; flex: none; }

    .wx-form .form-label { font-weight: 500; font-size: .875rem; }
    .wx-form .form-control,
    .wx-form .form-select { min-height: 40px; }
    .wx-form .form-hint { font-size: .8125rem; }
    .wx-form .invalid-feedback { display: block; font-size: .8125rem; }
    .wx-license-input { font-family: var(--tblr-font-monospace); letter-spacing: .03em; }

    .wx-drivers {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: .5rem;
    }
    .wx-driver {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: .125rem;
        padding: .75rem .875rem;
        border: 1px solid var(--wx-line);
        border-radius: 10px;
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s;
    }
    .wx-driver input { position: absolute; opacity: 0; pointer-events: none; }
    .wx-driver-name { font-weight: 600; font-size: .875rem; }
    .wx-driver-note { font-size: .75rem; color: var(--wx-muted); }
    .wx-driver:hover { border-color: var(--tblr-primary); }
    .wx-driver.is-selected {
        border-color: var(--tblr-primary);
        box-shadow: 0 0 0 1px var(--tblr-primary);
        background: rgba(var(--tblr-primary-rgb), .05);
    }
    .wx-driver input:focus-visible + .wx-driver-name { text-decoration: underline; }

    .wx-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1px;
        margin-top: 1rem;
        border: 1px solid var(--wx-line);
        border-radius: 10px;
        overflow: hidden;
        background: var(--wx-line);
    }
    .wx-summary div {
        padding: .75rem 1rem;
        background: var(--wx-surface);
        min-width: 0;
    }
    .wx-summary dt { font-size: .75rem; color: var(--wx-muted); font-weight: 400; }
    .wx-summary dd { margin: 0; font-size: .875rem; font-weight: 500; overflow-wrap: anywhere; }

    .wx-strength {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 4px;
        margin-top: .5rem;
    }
    .wx-strength span {
        height: 4px;
        border-radius: 2px;
        background: var(--wx-line);
    }
    .wx-strength.s-1 span:nth-child(-n+1) { background: var(--wx-danger); }
    .wx-strength.s-2 span:nth-child(-n+2) { background: var(--tblr-warning); }
    .wx-strength.s-3 span:nth-child(-n+3) { background: var(--tblr-lime, var(--wx-success)); }
    .wx-strength.s-4 span { background: var(--wx-success); }

    .wx-busy {
        align-items: center;
        gap: .625rem;
        margin-top: 1rem;
        font-size: .875rem;
        color: var(--wx-muted);
    }

    .wx-done-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(var(--tblr-success-rgb), .12);
        color: var(--wx-success);
        font-size: 1.75rem;
        margin-bottom: 1rem;
    }
    .wx-next {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .wx-next > li {
        display: flex;
        gap: .875rem;
    }
    .wx-next-num {
        flex: none;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--wx-soft);
        border: 1px solid var(--wx-line);
        font-size: .75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .wx-next-body { min-width: 0; flex: 1; font-size: .875rem; }
    .wx-next-body strong { display: block; }
    .wx-next-body p { color: var(--wx-muted); margin: .125rem 0 0; }

    /* Small screens: steps collapse into a progress bar */
    @media (max-width: 860px) {
        .wx-shell { padding: 0; align-items: stretch; }
        .wx-card {
            grid-template-columns: 1fr;
            grid-template-rows: auto 1fr;
            border-radius: 0;
            border: 0;
            box-shadow: none;
            min-height: 100vh;
        }
        .wx-aside {
            padding: .875rem 1rem;
            gap: 0;
            border-right: 0;
            border-bottom: 1px solid var(--wx-line);
        }
        .wx-steps, .wx-aside-foot { display: none; }
        .wx-progress {
            display: block;
            padding: 1.25rem 1rem 0;
        }
        .wx-progress-bar {
            height: 4px;
            border-radius: 2px;
            background: var(--wx-line);
            overflow: hidden;
        }
        .wx-progress-bar span {
            display: block;
            height: 100%;
            background: var(--tblr-primary);
            border-radius: 2px;
        }
        .wx-head { padding: 1rem 1rem 0; }
        .wx-head .wx-eyebrow { display: none; }
        .wx-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: .75rem;
            color: var(--wx-muted);
            margin-bottom: .5rem;
        }
        .wx-body { padding: 1.25rem 1rem 1.5rem; }
        .wx-actions {
            position: sticky;
            bottom: 0;
            padding: .75rem 1rem calc(.75rem + env(safe-area-inset-bottom));
        }
        .wx-summary { grid-template-columns: 1fr; }
    }
</style>
