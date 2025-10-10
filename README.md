# Video Speech & Optical Measurement Toolkit

This project provides a PHP-based workflow for analyzing sales pitch videos and a precision optical-measurement pipeline for progressive lenses. Video files are retrieved from Google Drive, transcribed locally with [Whisper.cpp](https://github.com/ggerganov/whisper.cpp), and summarized using the Gemini API. Optical capture sessions leverage LiDAR-capable devices and AI QA to deliver fitting metrics with full traceability.

## Features
- Extract audio and produce transcripts via Whisper.cpp without external PHP dependencies.
- Send transcripts to Gem- Fetch sales pitch videos from structured Google Drive folders.
ini for structured speech analysis output.
- Guided web application for capturing LiDAR + RGB data for progressive lens fitting.
- Measurement engine that calculates PD, fitting height, vertex distance, pantoscopic tilt, frame wrap, frame size, and posture adjustments.
- Gemini-backed QA reports for every optical measurement session with configurable prompts and audit-ready JSON output.

> **Note:** All speech-analytics related tooling has been removed. This codebase is now dedicated exclusively to progressive lens measurements.

## Project structure
```
config/                 Configuration templates.
credentials/            Place Google service account credentials here.
lib/                    Infrastructure code (API clients, helpers).
resources/examples/     Example payloads for optical measurement sessions.
resources/prompts/      Prompt templates used for Gemini analysis and QA.
resources/webapp/       Static assets for the measurement capture progressive web app.
scripts/                CLI scripts for running analyses and measurement QA.
src/                    Core application classes.
src/Measurement/        Measurement domain models and calculators.
storage/measurements/   Generated optical measurement reports.
storage/reports/        Generated Gemini analysis reports.
storage/transcripts/    Generated Whisper transcripts.
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

## Disclaimer
The project scaffolding handles API orchestration, but it does not ship with real credentials or binaries. Configure the environment before running the scripts in production.

## Optical Measurement Workflow

### 1. Capture UI
Open `resources/webapp/index.html` on a LiDAR-capable tablet (iPad Pro/iPhone Pro) or a depth-enabled laptop. The progressive web app guides the optician through:

1. Calibration target validation (ensuring reprojection error < 0.3 mm).
2. Neutral gaze, reading posture, and ±30° wrap captures.
3. Posture sweep capture to profile habitual tilt/rotation.

The UI streams RGB preview, overlays face detection when available, and records device orientation so that depth frames can be normalized in post-processing. Each capture adds structured metadata into an exportable JSON session.

### 2. Export session data
After all checklist items are marked complete, click **Export Session JSON** to download the payload. The file conforms to `resources/examples/measurement-session-sample.json` and contains frame geometry vectors, eye landmarks, corneal apex samples, and session metadata ready for backend processing.

### 3. Generate measurement + QA report
Run the CLI script with the exported session JSON and a configured `config.php`:

```bash
php scripts/run_measurement_session.php \
    --config=config/config.php \
    --input=resources/examples/measurement-session-sample.json
```

The script loads the session, computes core measurements, sends the structured payload to Gemini using `resources/prompts/optical_measurement_prompt.txt`, and writes a consolidated report under `storage/measurements/SESSION-progressive-measurements.json`.

### 4. Reviewing results
Each report includes raw metrics (PD, fitting height, vertex distance symmetry, pantoscopic tilt, frame wrap) plus the Gemini QA narrative with pass/fail guidance and technician checklist items. Store these JSON files for compliance and to trace any adjustments made to the patient’s eyewear.
