# Interactive Tool Zapier Bridge: QA candidate

This is a WordPress proof of concept. It is not a student release. Its receiver has only been exercised using PHP stubs and its sample tool using a JavaScript test harness. It has not been installed in WordPress or tested end to end with a live Zap. Do not claim production readiness.

The separately supplied quiz demo can submit a selected answer and the on screen result to this receiver. The receiver keeps the configured Zapier Catch Hook URL on the server and sends a JSON payload through the WordPress HTTP API. The private URL is not embedded in the page. The WordPress administrator must configure a real Catch Hook URL and enable submissions. The feature is disabled by default.

Security characteristics and remaining review: the endpoint accepts anonymous public traffic, and the honeypot and per IP transient limit are only partial abuse controls. Transient limits are not atomic, addresses may be shared by visitors behind a proxy, and distributed traffic is not prevented. A deployment should add appropriate host level rate controls and be reviewed for the site's privacy practices. Disabling the plugin closes the endpoint. The webhook is stored in the WordPress options database, accessible to privileged WordPress/database administrators. Check hosting and caching compatibility, and test actual Zap action mapping and delivery before use.

The ZIP contains only the plugin source. The demo HTML is separate and its placeholder URL must be replaced with the site's actual receiver address before a test. Do not include a Zapier URL in the demo HTML. For sites installed in a subdirectory or with customised REST URLs, use the exact receiver address shown in the plugin settings.

Verification performed: PHP 8.2 syntax check; PHP stub tests for URL allowlist, payload, disabled state, validation, request size, honeypot, upstream errors and basic rate limiting; JavaScript harness tests for two results, stale result clearing, payload mapping and retry after failure. This is not evidence of WordPress installation, actual network delivery, accessibility conformance or cross platform support.

The owner authorised a temporary encrypted secret for a test run with fictional submissions.

The automation delivery check is also being run with a temporary test Zap.
