# The easy way to go live (no terminal, no server admin)

You do everything by clicking on websites. Two accounts, about 15 minutes.

## What it costs
Roughly **US $14/month** on Render (a small web app + a small database that stay
online 24×7). You can start on this and grow later. No credit card is charged
until you pick paid plans.

---

## Part 1 — Put the code on GitHub (if it isn't already)
Your code already lives in a GitHub repository (that's where this file is). You
just need to be able to sign in to that GitHub account. If you can open the repo
in your browser, you're good.

## Part 2 — Deploy on Render
1. Go to **https://render.com** and click **Get Started** → **Sign in with
   GitHub**. Approve access to your repository.
2. In the Render dashboard click **New +** → **Blueprint**.
3. Choose your repository from the list. Render finds the `render.yaml` file
   automatically and shows what it will create (a web app + a database).
4. It will ask you to enter one value: **`DJANGO_SUPERUSER_PASSWORD`** — this is
   the password you'll use to log in as admin. Type a strong password and
   remember it.
5. Click **Apply**. Render now builds and starts everything (a few minutes).
   When the web service shows **"Live"**, it's running.

At this point your app is already online at a temporary address like
`https://inspexops.onrender.com` — open it and log in with username `admin` and
the password from step 4.

## Part 3 — Connect your own domain (schedule.mghaiapps.com)
1. In Render, open the **inspexops** web service → **Settings** → scroll to
   **Custom Domains** → **Add Custom Domain**.
2. Type `schedule.mghaiapps.com` and confirm. Render shows you a **CNAME target**
   (something like `inspexops.onrender.com`).
3. Go to your domain provider (where you manage `mghaiapps.com`) and add one DNS
   record:
   - **Type:** CNAME
   - **Name / Host:** `schedule`
   - **Value / Target:** the address Render showed you
4. Save. Within a few minutes Render verifies it and turns on HTTPS
   automatically.

Open **https://schedule.mghaiapps.com/** — done. 🎉

---

## After it's live
- **Add users, edit dropdowns, manage everything:** log in and go to the
  **Admin / Config** link (the `/admin/` page).
- **Change a setting later** (e.g. email): Render dashboard → your web service →
  **Environment** → edit the value → it redeploys automatically.
- **Daily reminder emails:** Render dashboard → **New +** → **Cron Job**, point
  it at the same repo, command `python manage.py daily_digest`, schedule
  `0 19 * * *` (7 pm daily).

## If you get stuck
Send me a screenshot of whatever Render shows and I'll tell you exactly what to
click next. You do not need to understand any of the technical parts — I'll
guide each step.
