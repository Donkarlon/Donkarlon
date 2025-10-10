# Video Speech & Optical Measurement Toolkit

This project provides a PHP-based workflow for analyzing sales pitch videos and a precision optical-measurement pipeline for progressive lenses. Video files are retrieved from Google Drive, transcribed locally with [Whisper.cpp](https://github.com/ggerganov/whisper.cpp), and summarized using the Gemini API. Optical capture sessions leverage LiDAR-capable devices and AI QA to deliver fitting metrics with full traceability.

## Features
- Fetch sales pitch videos from structured Google Drive folders.
- Extract audio and produce transcripts via Whisper.cpp without external PHP dependencies.
- Send transcripts to Gemini for structured speech analysis output.
- Guided web application for capturing LiDAR + RGB data for progressive lens fitting.
- Measurement engine that calculates PD, fitting height, vertex distance, pantoscopic tilt, frame wrap, frame size, and posture adjustments.
- Gemini-backed QA reports for every optical measurement session with configurable prompts and audit-ready JSON output.

## Project Structure
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
- PHP 8.1 or higher with `curl`, `json`, and `openssl` extensions enabled.
- [Whisper.cpp](https://github.com/ggerganov/whisper.cpp) compiled locally, with the binary path configured in `config/config.php`.
- [FFmpeg](https://ffmpeg.org/) installed and available in your `PATH` for audio extraction from videos.
- A Google Cloud project with the Drive API enabled and a service account JSON credential that has access to the desired folders.
- A Gemini API key stored in the `GEMINI_API_KEY` environment variable.

## Setup
1. Copy the configuration template and update values as needed:
   ```bash
   cp config/config.example.php config/config.php
   ```
2. Place your Google service account credential file at `credentials/google-service-account.json` (or update the path in the configuration).
3. Build Whisper.cpp and update the `whisper.binary_path` and `whisper.model_path` settings to match your environment.
4. Ensure `ffmpeg` is installed and accessible via the command line.
5. Export your Gemini API key:
   ```bash
   export GEMINI_API_KEY="your-api-key"
   ```

## Running an Analysis
Use the provided CLI script to pull the latest video from a Drive folder, transcribe it, and generate a Gemini analysis:

```bash
php scripts/analyze_sales_pitch.php \
    --config=config/config.php \
    --pitch="north-america-q1" \
    --limit=1
```

- `--pitch` corresponds to a key defined in `google_drive.folder_mappings` (or is optional when using `--local-dir`).
- `--limit` determines how many recent files to process from the folder.
- `--local-dir` lets you process the newest files from a local directory instead of Google Drive.

To run the analysis against videos stored locally, provide a directory path:

```bash
php scripts/analyze_sales_pitch.php \
    --config=config/config.php \
    --local-dir=/path/to/videos \
    --limit=1
```

When `--local-dir` is provided, the script sorts files by their modification time and processes the most recent entries without downloading from Google Drive.

Transcripts will be stored under `storage/transcripts/` and the Gemini report will be saved to `storage/reports/` with matching base filenames.

## Development Notes
- Composer is intentionally not used; the project relies on native PHP features.
- Ensure directories inside `storage/` are writable by the PHP process.
- The provided prompt can be customized by editing `resources/prompts/sales_pitch_prompt.txt`.

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
