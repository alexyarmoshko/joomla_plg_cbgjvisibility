(() => {
    "use strict";

    const roots = document.querySelectorAll(".js-cbgjvisibility-sanitization-test");

    if (roots.length === 0) {
        return;
    }

    const hasJoomlaText = typeof window.Joomla !== "undefined"
        && window.Joomla !== null
        && window.Joomla.Text
        && typeof window.Joomla.Text._ === "function";

    const getText = (key, fallback) => (hasJoomlaText ? window.Joomla.Text._(key) : fallback);
    const runningLabel = getText("PLG_SYSTEM_CBGJVISIBILITY_TEST_RUNNING", "Fetching front page as guest...");
    const inconclusiveFallback = getText(
        "PLG_SYSTEM_CBGJVISIBILITY_TEST_INCONCLUSIVE",
        "No event data found on front page. Test inconclusive."
    );

    roots.forEach((root) => {
        const url = root.dataset.cbgjvisibilityUrl || "";
        const button = root.querySelector(".js-cbgjvisibility-test-button");
        const output = root.querySelector(".js-cbgjvisibility-test-output");

        if (!url || !button || !output) {
            return;
        }

        button.addEventListener("click", async () => {
            button.disabled = true;
            output.textContent = runningLabel;

            try {
                const response = await fetch(url, {
                    credentials: "same-origin",
                    method: "GET",
                    headers: { Accept: "application/json" },
                });
                const json = await response.json();
                const payload = Array.isArray(json.data)
                    ? (json.data.length > 0 ? json.data[0] : null)
                    : (json.data ?? null);

                if (!payload) {
                    output.textContent = JSON.stringify(json, null, 2);
                    return;
                }

                if (payload.error) {
                    output.textContent = payload.error;
                    return;
                }

                const lines = [];

                if (payload.marker_found === false) {
                    lines.push(payload.message || inconclusiveFallback);
                } else {
                    lines.push(payload.message || "");
                    lines.push("");
                    (payload.checks || []).forEach((check) => {
                        lines.push("[" + check.status + "] " + check.class);
                    });
                }

                output.textContent = lines.join("\n");
            } catch (error) {
                output.textContent = String(error);
            } finally {
                button.disabled = false;
            }
        });
    });
})();
