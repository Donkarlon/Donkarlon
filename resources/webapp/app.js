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
  lastDetectionForOverlay: null,
  activeMetrics: null,
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
  state.activeMetrics = computeMeasurementsFromCapture(capture);
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
    if (state.lastDetection) {
      state.lastDetectionForOverlay = state.lastDetection;
    }
    renderOverlay(state.lastDetection);
  } catch (error) {
    console.warn('Face detector error', error);
    state.lastDetection = null;
    renderOverlay(null);
  }

  state.rafHandle = requestAnimationFrame(detectFaceLoop);
}

function renderOverlay(detection) {
  overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
  const overlayDetection = detection || state.lastDetectionForOverlay;
  if (!overlayDetection) {
    drawPinnedMetrics(null);
    return;
  }

  const { x, y, width, height } = overlayDetection.boundingBox;
  overlayCtx.strokeStyle = '#3a68f9';
  overlayCtx.lineWidth = 2;
  overlayCtx.strokeRect(x, y, width, height);

  drawGrid(x, y, width, height);
  drawLandmarks(overlayDetection);
  drawMeasurementAugmentation(overlayDetection);
}

function drawGrid(x, y, width, height) {
  const cols = 3;
  const rows = 3;
  overlayCtx.save();
  overlayCtx.strokeStyle = 'rgba(58, 104, 249, 0.5)';
  overlayCtx.lineWidth = 1;
  for (let i = 1; i < cols; i += 1) {
    const gx = x + (width / cols) * i;
    overlayCtx.beginPath();
    overlayCtx.moveTo(gx, y);
    overlayCtx.lineTo(gx, y + height);
    overlayCtx.stroke();
  }
  for (let j = 1; j < rows; j += 1) {
    const gy = y + (height / rows) * j;
    overlayCtx.beginPath();
    overlayCtx.moveTo(x, gy);
    overlayCtx.lineTo(x + width, gy);
    overlayCtx.stroke();
  }
  overlayCtx.restore();
}

function drawLandmarks(detection) {
  if (!detection.landmarks) return;
  overlayCtx.save();
  overlayCtx.fillStyle = '#00ffd0';
  detection.landmarks.forEach((landmark) => {
    if (!landmark.locations) return;
    landmark.locations.forEach((loc) => {
      overlayCtx.beginPath();
      overlayCtx.arc(loc.x, loc.y, 3, 0, Math.PI * 2);
      overlayCtx.fill();
    });
  });
  overlayCtx.restore();
}

function drawMeasurementAugmentation(detection) {
  const { x, y, width, height } = detection.boundingBox;
  const leftEye = detection.landmarks?.find((l) => l.type === 'leftEye');
  const rightEye = detection.landmarks?.find((l) => l.type === 'rightEye');
  const nose = detection.landmarks?.find((l) => l.type === 'nose');

  const fallbackLeft = { x: x + width * 0.35, y: y + height * 0.45 };
  const fallbackRight = { x: x + width * 0.65, y: y + height * 0.45 };
  const left = leftEye?.locations?.[0] || fallbackLeft;
  const right = rightEye?.locations?.[0] || fallbackRight;
  const nosePoint = nose?.locations?.[0] || { x: x + width * 0.5, y: y + height * 0.55 };

  overlayCtx.save();
  overlayCtx.strokeStyle = '#00ffd0';
  overlayCtx.lineWidth = 2;

  // Pupillary distance line
  overlayCtx.beginPath();
  overlayCtx.moveTo(left.x, left.y);
  overlayCtx.lineTo(right.x, right.y);
  overlayCtx.stroke();

  // Fitting height lines (pupil to lower rim)
  const leftBottom = { x: left.x, y: y + height };
  const rightBottom = { x: right.x, y: y + height };
  overlayCtx.beginPath();
  overlayCtx.moveTo(left.x, left.y);
  overlayCtx.lineTo(leftBottom.x, leftBottom.y);
  overlayCtx.moveTo(right.x, right.y);
  overlayCtx.lineTo(rightBottom.x, rightBottom.y);
  overlayCtx.stroke();

  overlayCtx.restore();

  drawPinnedMetrics({
    anchor: { x, y, width, height, left, right, nose: nosePoint, leftBottom, rightBottom },
  });
}

function drawPinnedMetrics(geometry) {
  const metrics = state.activeMetrics;
  if (!metrics) return;

  if (!geometry) {
    drawMetricCard(overlayCanvas.width - 220, 20, metrics);
    return;
  }

  const {
    anchor: { x, y, width, height, left, right, nose, leftBottom, rightBottom },
  } = geometry;

  const midX = (left.x + right.x) / 2;
  const midY = (left.y + right.y) / 2 - 12;
  drawLabel(midX, midY, `PD ${metrics.binocularPD.toFixed(1)} mm`);

  drawLabel(left.x - 70, (left.y + leftBottom.y) / 2, `FH L ${metrics.fittingHeight.left.toFixed(1)} mm`);
  drawLabel(right.x + 70, (right.y + rightBottom.y) / 2, `FH R ${metrics.fittingHeight.right.toFixed(1)} mm`);

  drawLabel(nose.x - 80, nose.y - 30, `Mono L ${metrics.monocularPD.left.toFixed(1)} mm`);
  drawLabel(nose.x + 80, nose.y - 30, `Mono R ${metrics.monocularPD.right.toFixed(1)} mm`);

  drawLabel(x - 10, y + height / 2, `Vertex ${metrics.vertexDistance.average.toFixed(1)} mm`, 'right');
  drawLabel(x + width + 10, y + height / 2, `Tilt ${metrics.pantoscopicTilt.toFixed(1)}°`, 'left');
  drawLabel(x + width / 2, y + height + 30, `Wrap ${metrics.frameWrap.toFixed(1)}°`);
}

function drawMetricCard(x, y, metrics) {
  const lines = [
    `PD: ${metrics.binocularPD.toFixed(1)} mm`,
    `Mono PD L/R: ${metrics.monocularPD.left.toFixed(1)} / ${metrics.monocularPD.right.toFixed(1)} mm`,
    `Fitting Height L/R: ${metrics.fittingHeight.left.toFixed(1)} / ${metrics.fittingHeight.right.toFixed(1)} mm`,
    `Vertex Distance Avg: ${metrics.vertexDistance.average.toFixed(1)} mm`,
    `Pantoscopic Tilt: ${metrics.pantoscopicTilt.toFixed(1)}°`,
    `Frame Wrap: ${metrics.frameWrap.toFixed(1)}°`,
  ];

  const width = 200;
  const lineHeight = 18;
  const height = lineHeight * lines.length + 16;

  overlayCtx.save();
  overlayCtx.fillStyle = 'rgba(0, 0, 0, 0.65)';
  overlayCtx.strokeStyle = 'rgba(58, 104, 249, 0.8)';
  overlayCtx.lineWidth = 1;
  overlayCtx.beginPath();
  roundedRectPath(overlayCtx, x, y, width, height, 12);
  overlayCtx.fill();
  overlayCtx.stroke();

  overlayCtx.fillStyle = '#f7fbff';
  overlayCtx.font = '13px "SF Pro Display", "Segoe UI", sans-serif';
  lines.forEach((line, index) => {
    overlayCtx.fillText(line, x + 12, y + 20 + index * lineHeight);
  });
  overlayCtx.restore();
}

function drawLabel(x, y, text, align = 'center') {
  overlayCtx.save();
  overlayCtx.font = '14px "SF Pro Display", "Segoe UI", sans-serif';
  overlayCtx.textAlign = align;
  overlayCtx.textBaseline = 'middle';
  const paddingX = 10;
  const textMetrics = overlayCtx.measureText(text);
  const boxWidth = textMetrics.width + paddingX * 2;
  const boxHeight = 24;
  const drawX = align === 'center' ? x - boxWidth / 2 : align === 'right' ? x - boxWidth : x;
  const drawY = y - boxHeight / 2;

  overlayCtx.fillStyle = 'rgba(0, 0, 0, 0.6)';
  overlayCtx.strokeStyle = 'rgba(0, 255, 208, 0.8)';
  overlayCtx.lineWidth = 1;
  overlayCtx.beginPath();
  roundedRectPath(overlayCtx, drawX, drawY, boxWidth, boxHeight, 12);
  overlayCtx.fill();
  overlayCtx.stroke();

  overlayCtx.fillStyle = '#eaffff';
  overlayCtx.fillText(text, drawX + paddingX, y);
  overlayCtx.restore();
}

function computeMeasurementsFromCapture(capture) {
  const { pupil_centers, nose_bridge_point, lower_rim_points, corneal_apexes, lens_inner_samples, frame_geometry } =
    capture;
  const leftPupil = pupil_centers.left;
  const rightPupil = pupil_centers.right;
  const nose = nose_bridge_point || [0, 0, 0];

  const binocularPD = distanceBetween(leftPupil, rightPupil);
  const monocularPD = {
    left: Math.abs(leftPupil[0] - nose[0]),
    right: Math.abs(rightPupil[0] - nose[0]),
  };

  const fittingHeight = {
    left: Math.abs((leftPupil?.[1] ?? 0) - (lower_rim_points?.left?.[1] ?? 0)),
    right: Math.abs((rightPupil?.[1] ?? 0) - (lower_rim_points?.right?.[1] ?? 0)),
  };

  const vertexLeft = Math.abs((corneal_apexes?.left?.[2] ?? 0) - (lens_inner_samples?.center?.[2] ?? 0));
  const vertexRight = Math.abs((corneal_apexes?.right?.[2] ?? 0) - (lens_inner_samples?.center?.[2] ?? 0));
  const vertexDistance = {
    left: vertexLeft,
    right: vertexRight,
    average: (vertexLeft + vertexRight) / 2,
  };

  const verticalAxis = normalizeVector(frame_geometry?.vertical_axis || [0, 1, 0]);
  const horizontalAxis = normalizeVector(frame_geometry?.horizontal_axis || [1, 0, 0]);
  const worldUp = [0, 1, 0];

  const pantoscopicTilt = radiansToDegrees(Math.acos(clamp(dot(verticalAxis, worldUp), -1, 1)));
  const frameWrap = radiansToDegrees(Math.acos(clamp(dot(horizontalAxis, [1, 0, 0]), -1, 1)));

  return {
    binocularPD,
    monocularPD,
    fittingHeight,
    vertexDistance,
    pantoscopicTilt,
    frameWrap,
  };
}

function distanceBetween(a, b) {
  if (!a || !b) return 0;
  const dx = (a[0] ?? 0) - (b[0] ?? 0);
  const dy = (a[1] ?? 0) - (b[1] ?? 0);
  const dz = (a[2] ?? 0) - (b[2] ?? 0);
  return Math.sqrt(dx * dx + dy * dy + dz * dz);
}

function dot(a, b) {
  return (a?.[0] ?? 0) * (b?.[0] ?? 0) + (a?.[1] ?? 0) * (b?.[1] ?? 0) + (a?.[2] ?? 0) * (b?.[2] ?? 0);
}

function normalizeVector(v) {
  const mag = Math.hypot(v?.[0] ?? 0, v?.[1] ?? 0, v?.[2] ?? 0);
  if (mag === 0) return [0, 0, 0];
  return [(v[0] ?? 0) / mag, (v[1] ?? 0) / mag, (v[2] ?? 0) / mag];
}

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max);
}

function radiansToDegrees(rad) {
  return (rad * 180) / Math.PI;
}

function roundedRectPath(ctx, x, y, width, height, radius) {
  const r = Math.min(radius, width / 2, height / 2);
  ctx.moveTo(x + r, y);
  ctx.lineTo(x + width - r, y);
  ctx.quadraticCurveTo(x + width, y, x + width, y + r);
  ctx.lineTo(x + width, y + height - r);
  ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
  ctx.lineTo(x + r, y + height);
  ctx.quadraticCurveTo(x, y + height, x, y + height - r);
  ctx.lineTo(x, y + r);
  ctx.quadraticCurveTo(x, y, x + r, y);
  ctx.closePath();
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
  state.activeMetrics = null;
  state.lastDetectionForOverlay = null;
  measurementJson.textContent = 'Awaiting capture…';
  instructionFeed.innerHTML = '';
  captureButton.disabled = true;
  exportButton.disabled = true;
  uploadButton.disabled = true;
  statusPill.textContent = 'Session reset';
  renderOverlay(null);
}

function cloneCaptureTemplate(obj) {
  return JSON.parse(JSON.stringify(obj));
}
