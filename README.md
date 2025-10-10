# Progressive Lens Measurement Platform

This repository contains a self-contained workflow for capturing, calculating, and auditing all measurements required to dispense custom progressive glasses. LiDAR-capable capture devices feed structured sessions into a PHP measurement engine that produces traceable metrics and a Gemini-backed quality assurance (QA) narrative for the optician.

## Key capabilities
- Responsive capture web app that guides LiDAR/RGB depth collection for progressive fitting.
- Measurement engine that calculates monocular/binocular pupillary distance, fitting height, vertex distance, pantoscopic tilt, frame wrap, frame size, and habitual posture adjustments.
- Gemini QA integration that validates every session and stores auditable summaries alongside the raw metrics.
- File-based storage layout that keeps capture exports, QA prompts, and generated reports organized for compliance.

> **Note:** All speech-analytics related tooling has been removed. This codebase is now dedicated exclusively to progressive lens measurements.

## Project structure
```
config/                 Configuration templates for the measurement workflow.
lib/                    Shared infrastructure utilities (e.g., Gemini client).
resources/examples/     Sample capture exports for testing the measurement CLI.
resources/prompts/      Prompt templates used for Gemini QA.
resources/webapp/       Static assets for the LiDAR-ready capture web app.
scripts/                CLI entry points for the measurement workflow.
src/                    Core PHP classes, including measurement domain models.
storage/measurements/   Output directory for generated measurement + QA reports.
```

## Prerequisites
- PHP 8.1 or higher with the `curl`, `json`, and `openssl` extensions enabled.
- A Gemini API key stored in the `GEMINI_API_KEY` environment variable.
- LiDAR-capable or depth-enabled hardware to operate the capture web app (for live sessions).

## Setup
1. Copy the configuration template and update the paths and prompt locations as needed:
   ```bash
   cp config/config.example.php config/config.php
   ```
2. Export your Gemini API key so it is available to the CLI:
   ```bash
   export GEMINI_API_KEY="your-api-key"
   ```
3. Verify that the `storage/measurements/` directory is writable by the PHP process. A `.gitkeep` file is provided to maintain the directory in version control.

## Capture workflow
1. Open `resources/webapp/index.html` on a supported device.
2. Follow the on-screen checklist to perform calibration, neutral gaze, reading posture, wrap, and posture captures.
3. Export the session JSON once every checklist item is complete. The payload will follow the schema of `resources/examples/measurement-session-sample.json`.

## Generate a measurement report
Run the CLI script with your configuration file and exported session JSON:

```bash
php scripts/run_measurement_session.php \
    --config=config/config.php \
    --input=resources/examples/measurement-session-sample.json
```

The script will:
1. Load the measurement session payload.
2. Calculate all optical metrics.
3. Submit the structured payload to Gemini using `resources/prompts/optical_measurement_prompt.txt`.
4. Persist the combined metrics and QA summary to `storage/measurements/<session-id>-progressive-measurements.json`.

Review the generated JSON file to confirm the numeric measurements and AI QA commentary before dispensing the lenses.

## Development notes
- Composer is not required; autoloading is handled via simple script-based registries.
- The measurement domain can be extended by adding new calculators within `src/Measurement/MeasurementCalculator.php`.
- Store calibration artifacts, device certifications, and additional prompts within the `resources/` directory as your optical workflow evolves.
