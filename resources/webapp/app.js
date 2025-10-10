const STEPS = [
  {
    key: 'calibration',
    title: 'Calibration Scan',
    description:
      'Place the calibration target at 40cm. Align until reprojection error is below 0.3mm and tap Capture.',
  },
  {
    key: 'neutral',
    title: 'Neutral Head Pose',
    description: 'Patient looks straight ahead through distance zone. Ensure both pupils are visible.',
  },
  {
    key: 'reading',
    title: 'Reading Posture',
    description: 'Ask the patient to dip their chin 10–15° as if reading a phone.',
  },
  {
    key: 'left30',
    title: 'Left 30° Wrap',
    description: 'Rotate device around the patient to capture 30° left. Confirm frame temple visible.',
  },
  {
    key: 'right30',
    title: 'Right 30° Wrap',
    description: 'Rotate device around the patient to capture 30° right.',
  },
  {
    key: 'posture',
    title: 'Posture Sweep',
    description: 'Record natural posture while patient relaxes shoulders.',
  },
];

const DEFAULT_CAPTURE = {
  frame_geometry: {
    origin: [0, 0, 0],
    normal: [0, 0, 1],
    vertical_axis: [0, 1, 0],
    horizontal_axis: [1, 0, 0],
  },
  pupil_centers: {
    left: [-30, 18, 60],
    right: [30, 18, 60],
  },
  corneal_apexes: {
    left: [-30, 18, 56],
    right: [30, 18, 56],
  },
  nose_bridge_point: [0, 17, 62],
  lower_rim_points: {
    left: [-30, -8, 60],
    right: [30, -8, 60],
  },
  lens_inner_samples: {
    center: [0, 12, 59],
    top: [0, 38, 59],
    bottom: [0, -22, 59],
    temporal: [46, 11, 59],
    nasal: [-46, 11, 59],
  },
  head_pose_vectors: {
    forward: [0, 0, 1],
    up: [0, 1, 0],
  },
  metadata: {
    capture: 'template',
    quality: 'unknown',
  },
};

const state = {
  stream: null,
  currentStepIndex: -1,
  session: null,
  orientation: { alpha: 0, beta: 0, gamma: 0 },
  faceDetector: null,
  lastDetection: null,
  rafHandle: null,
};

const previewVideo = document.getElementById('preview');
const overlayCanvas = document.getElementById('overlay');
const overlayCtx = overlayCanvas.getContext('2d');
const statusPill = document.getElementById('status-pill');
const instructionFeed = document.getElementById('instruction-feed');
const measurementJson = document.getElementById('measurement-json');
const exportButton = document.getElementById('export-session');
const uploadButton = document.getElementById('upload-session');

const startButton = document.getElementById('start-session');
const captureButton = document.getElementById('capture-frame');
const resetButton = document.getElementById('reset-session');

const patientNameInput = document.getElementById('patient-name');
const primaryUseInput = document.getElementById('primary-use');
const patientNotesInput = document.getElementById('patient-notes');

startButton.addEventListener('click', async () => {
  await initializeSensors();
  initializeSession();
  nextStep();
});

captureButton.addEventListener('click', async () => {
  await captureCurrentStep();
});

resetButton.addEventListener('click', () => {
  resetSession();
});

exportButton.addEventListener('click', () => {
  if (!state.session) return;
  const blob = new Blob([JSON.stringify(state.session, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${state.session.session_id}.json`;
  a.click();
  URL.revokeObjectURL(url);
});

uploadButton.addEventListener('click', async () => {
  if (!state.session) return;
  const endpoint = document.body.dataset.endpoint || 'http://localhost:8080/measurements';
  try {
    statusPill.textContent = 'Uploading session…';
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(state.session),
    });
    if (!res.ok) throw new Error(`Upload failed with status ${res.status}`);
    const data = await res.json().catch(() => ({ message: 'Uploaded' }));
    statusPill.textContent = data.message || 'Session uploaded';
  } catch (error) {
    console.error(error);
    statusPill.textContent = 'Upload failed – check console';
  }
});

async function initializeSensors() {
  if (state.stream) return;
  try {
    state.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
    previewVideo.srcObject = state.stream;
    await previewVideo.play();
    resizeOverlay();
    window.addEventListener('resize', resizeOverlay);
    statusPill.textContent = 'Camera ready';
    captureButton.disabled = false;
    resetButton.disabled = false;

    if ('FaceDetector' in window) {
      state.faceDetector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1, scoreThreshold: 0.6 });
      requestAnimationFrame(detectFaceLoop);
    }
  } catch (error) {
    console.error('Camera error', error);
    statusPill.textContent = 'Camera unavailable – use manual entry';
  }

  if (window.DeviceOrientationEvent) {
    window.addEventListener('deviceorientation', (event) => {
      state.orientation = {
        alpha: event.alpha ?? 0,
        beta: event.beta ?? 0,
        gamma: event.gamma ?? 0,
      };
    });
  }

  if (navigator.xr && navigator.xr.isSessionSupported) {
    navigator.xr.isSessionSupported('immersive-ar').then((supported) => {
      if (supported) {
        statusPill.textContent = 'LiDAR ready – start session';
      }
    });
  }
}

function initializeSession() {
  const now = new Date();
  state.session = {
    session_id: `session-${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(
      now.getDate()
    ).padStart(2, '0')}-${now.getTime()}`,
    calibration: {
      device: navigator.userAgent,
      scale_mm_per_unit: 1,
      reference_artifact_error_mm: null,
    },
    patient_profile: {
      name: patientNameInput.value || null,
      primary_use: primaryUseInput.value || null,
      notes: patientNotesInput.value || null,
    },
    captures: {},
  };

  exportButton.disabled = false;
  uploadButton.disabled = false;
  measurementJson.textContent = JSON.stringify(state.session, null, 2);
}

function nextStep() {
  state.currentStepIndex += 1;
  if (state.currentStepIndex >= STEPS.length) {
    statusPill.textContent = 'Session complete';
    captureButton.disabled = true;
    return;
  }

  const step = STEPS[state.currentStepIndex];
  statusPill.textContent = `${step.title}`;
  renderInstructions(step);
  markTimeline(step.key);
}

function renderInstructions(step) {
  instructionFeed.innerHTML = '';
  const template = document.getElementById('instruction-template');
  const clone = template.content.cloneNode(true);
  clone.querySelector('h3').textContent = step.title;
  clone.querySelector('p').textContent = step.description;
  instructionFeed.appendChild(clone);
}

function markTimeline(key) {
  document.querySelectorAll('[data-step]').forEach((item) => {
    item.classList.toggle('completed', Boolean(state.session?.captures[item.dataset.step]));
    if (item.dataset.step === key) {
      item.classList.add('current');
    } else {
      item.classList.remove('current');
    }
  });
}

async function captureCurrentStep() {
  const step = STEPS[state.currentStepIndex];
  if (!step || !state.session) return;

  const pose = buildPoseFromOrientation();
  const capture = buildCapturePayload(step.key, pose);
  state.session.captures[step.key] = capture;
  measurementJson.textContent = JSON.stringify(state.session, null, 2);
  markTimeline(step.key);
  nextStep();
}

function buildPoseFromOrientation() {
  const toRad = (value) => ((value ?? 0) * Math.PI) / 180;
  const { alpha, beta, gamma } = state.orientation;
  const yaw = toRad(alpha);
  const pitch = toRad(beta);
  const roll = toRad(gamma);

  const cy = Math.cos(yaw);
  const sy = Math.sin(yaw);
  const cp = Math.cos(pitch);
  const sp = Math.sin(pitch);
  const cr = Math.cos(roll);
  const sr = Math.sin(roll);

  const forward = [cy * cp, -sp, sy * cp];
  const up = [cy * sp * sr - sy * cr, cp * sr, sy * sp * sr + cy * cr];
  const horizontal = cross(up, forward);

  return { forward: normalize(forward), up: normalize(up), horizontal: normalize(horizontal) };
}

function buildCapturePayload(stepKey, pose) {
  const base = cloneCaptureTemplate(DEFAULT_CAPTURE);
  const detection = state.lastDetection;

  base.metadata = {
    capture: stepKey,
    timestamp: new Date().toISOString(),
    quality: detection ? qualityFromDetection(detection) : 'manual',
    low_confidence: detection ? detection.score < 0.85 : true,
    orientation: { ...state.orientation },
  };

  base.frame_geometry = {
    origin: [0, 0, 0],
    normal: pose.forward,
    vertical_axis: pose.up,
    horizontal_axis: pose.horizontal,
  };

  if (detection) {
    const scale = state.session?.calibration?.scale_mm_per_unit ?? 1;
    const { pupils, corneas } = deriveLandmarksFromDetection(detection, scale);
    base.pupil_centers = pupils;
    base.corneal_apexes = corneas;
    base.nose_bridge_point = [0, detection.boundingBox.y * scale, detection.boundingBox.depth ?? 60];
  }

  return base;
}

function deriveLandmarksFromDetection(detection, scale) {
  if (!detection.landmarks) {
    return {
      pupils: DEFAULT_CAPTURE.pupil_centers,
      corneas: DEFAULT_CAPTURE.corneal_apexes,
    };
  }

  const leftEye = detection.landmarks.find((l) => l.type === 'leftEye');
  const rightEye = detection.landmarks.find((l) => l.type === 'rightEye');
  const nose = detection.landmarks.find((l) => l.type === 'nose');
  const depth = 60;

  const pupils = {
    left: [-(leftEye?.locations?.[0]?.x ?? 30) * scale, (leftEye?.locations?.[0]?.y ?? 18) * scale, depth],
    right: [(rightEye?.locations?.[0]?.x ?? 30) * scale, (rightEye?.locations?.[0]?.y ?? 18) * scale, depth],
  };

  const corneas = {
    left: [pupils.left[0], pupils.left[1], depth - 3.2],
    right: [pupils.right[0], pupils.right[1], depth - 3.2],
  };

  return { pupils, corneas, nose };
}

function qualityFromDetection(detection) {
  const occluded = detection.landmarks?.some((landmark) => landmark.occluded);
  if (occluded) return 'occlusion';
  return detection.score > 0.9 ? 'high' : 'medium';
}

function resizeOverlay() {
  overlayCanvas.width = previewVideo.videoWidth;
  overlayCanvas.height = previewVideo.videoHeight;
}

async function detectFaceLoop() {
  if (!state.faceDetector || previewVideo.readyState < 2) {
    state.rafHandle = requestAnimationFrame(detectFaceLoop);
    return;
  }

  try {
    const detections = await state.faceDetector.detect(previewVideo);
    state.lastDetection = detections[0];
    drawDetection(state.lastDetection);
  } catch (error) {
    console.warn('Face detector error', error);
    state.lastDetection = null;
    overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
  }

  state.rafHandle = requestAnimationFrame(detectFaceLoop);
}

function drawDetection(detection) {
  overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
  if (!detection) return;
  const { x, y, width, height } = detection.boundingBox;
  overlayCtx.strokeStyle = '#3a68f9';
  overlayCtx.lineWidth = 2;
  overlayCtx.strokeRect(x, y, width, height);
}

function cross(a, b) {
  return [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
}

function normalize(v) {
  const mag = Math.hypot(v[0], v[1], v[2]);
  return mag === 0 ? [0, 0, 0] : [v[0] / mag, v[1] / mag, v[2] / mag];
}

function resetSession() {
  if (state.rafHandle) cancelAnimationFrame(state.rafHandle);
  state.currentStepIndex = -1;
  state.session = null;
  measurementJson.textContent = 'Awaiting capture…';
  instructionFeed.innerHTML = '';
  captureButton.disabled = true;
  exportButton.disabled = true;
  uploadButton.disabled = true;
  statusPill.textContent = 'Session reset';
}

function cloneCaptureTemplate(obj) {
  return JSON.parse(JSON.stringify(obj));
}
