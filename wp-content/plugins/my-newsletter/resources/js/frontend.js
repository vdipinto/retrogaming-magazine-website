// resources/js/frontend.js
import "../scss/frontend.scss";

document.addEventListener("submit", async (e) => {
  const form = e.target;
  if (!form.matches("form.ns-form")) return;

  e.preventDefault();

  const msg = form.querySelector(".ns-message");
  const btn = form.querySelector(".ns-submit");

  // From wp_localize_script in PHP
  const restUrl = (window.NSNewsletter && NSNewsletter.restUrl) || "/wp-json/mynews/v1/signup";
  const okText  = (window.NSNewsletter && NSNewsletter.ok)  || "Thanks! Check your inbox.";
  const errText = (window.NSNewsletter && NSNewsletter.err) || "Something went wrong.";

  // Build payload
  const payload = {
    first_name: form.first_name?.value?.trim() || "",
    last_name:  form.last_name?.value?.trim()  || "",
    email:      form.email?.value?.trim()      || "",
  };

  // Simple front-end email check (server still validates)
  if (!payload.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) {
    if (msg) { msg.textContent = "Please enter a valid email."; msg.classList.remove("success"); msg.classList.add("error"); }
    return;
  }

  // UI: loading state
  const prev = btn ? btn.textContent : null;
  if (btn) { btn.disabled = true; btn.textContent = (prev || "Subscribe") + "…"; }
  if (msg) { msg.textContent = ""; msg.classList.remove("success", "error"); }

  try {
    const res = await fetch(restUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(payload),
    });

    // Try to parse JSON even on error responses
    let json = null;
    try { json = await res.json(); } catch (_) {}

    if (res.ok && json && (json.success === true || json.success === "true")) {
      if (msg) { msg.classList.add("success"); msg.textContent = json.message || okText; }
      form.reset();
    } else {
      const serverMsg = json?.message || errText;
      if (msg) { msg.classList.add("error"); msg.textContent = serverMsg; }
    }
  } catch (err) {
    if (msg) { msg.classList.add("error"); msg.textContent = "Network error."; }
    console.error("MyNewsletter submit error:", err);
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = prev || "Subscribe"; }
  }
});
