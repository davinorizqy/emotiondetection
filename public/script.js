let model;
let faceClassifier;
let cvReady = false;
let emotionBuffer = [];
let stream = null;
let isCameraOn = false;

const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');
const resultDiv = document.getElementById('result');
const emotionDiv = document.getElementById('emotion');

const labels = ["angry", "happy", "neutral", "sad"];

// ============ LIGHTWEIGHT BACKGROUND PROCESSING ============
const procCanvas = document.createElement('canvas');
const pctx = procCanvas.getContext('2d');
const PROCESS_WIDTH = 200;
const PREDICT_INTERVAL = 300; // Predict emotion every 0.5 seconds
const DETECT_INTERVAL = 300; // Detect & draw bounding box every 300ms for real-time feel
// ========================================================

// Cache untuk menyimpan faces terakhir dan emotions
let lastFaces = [];
let faceEmotions = {}; // map face index to emotion

// ==========================
// OPENCV READY
// ==========================
function waitForOpenCV() {
    if (typeof cv !== "undefined") {
        cv['onRuntimeInitialized'] = () => {
            console.log("OpenCV Ready ✅");
            cvReady = true;
            loadHaarCascade();
        };
    } else {
        setTimeout(waitForOpenCV, 100);
    }
}

waitForOpenCV();

// ==========================
// LOAD HAAR
// ==========================
function loadHaarCascade() {
    fetch('/haarcascade/haarcascade_frontalface_default.xml')
        .then(res => res.arrayBuffer())
        .then(data => {
            cv.FS_createDataFile('/', 'face.xml', new Uint8Array(data), true, false, false);

            faceClassifier = new cv.CascadeClassifier();
            faceClassifier.load('face.xml');

            console.log("Haar Loaded ✅");

            loadModel();
        });
}

// ==========================
// LOAD MODEL
// ==========================
async function loadModel() {
    try {
        model = await tf.loadGraphModel('/model/model.json');
        console.log("Model loaded ✅");

        startCamera();
    } catch (error) {
        console.error("Model error:", error);
    }
}

// ==========================
// START CAMERA
// ==========================
async function startCamera() {

    if (isCameraOn) {
        // 🔴 STOP CAMERA
        stream.getTracks().forEach(track => track.stop());
        video.srcObject = null;

        isCameraOn = false;
        console.log("Camera OFF ❌");

        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;

        video.onloadedmetadata = () => {
            video.play();

            const overlay = document.getElementById("cameraOverlay");
            if (overlay) overlay.style.display = "none";
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Setup processing canvas (smaller for speed)
            procCanvas.width = PROCESS_WIDTH;
            procCanvas.height = Math.round(PROCESS_WIDTH * video.videoHeight / video.videoWidth);

            startPredictionLoop();

            console.log("Camera ON ✅ (Processing at " + procCanvas.width + "x" + procCanvas.height + ")");
        };

        isCameraOn = true;

    } catch (err) {
        console.error("Camera error:", err);
    }
}

// ==========================
// LOOP
// ==========================
function startPredictionLoop() {
    // Loop 1: Detect faces & update bounding boxes frequently (real-time)
    setInterval(() => {
        if (cvReady && faceClassifier && video.readyState === 4) {
            detectFaces();
        }
    }, DETECT_INTERVAL);

    // Loop 2: Predict emotions less frequently (only every 0.5 seconds)
    setInterval(() => {
        if (model && cvReady && faceClassifier && video.readyState === 4 && lastFaces.length > 0) {
            predictEmotions();
        }
    }, PREDICT_INTERVAL);
}

// ==========================
// DETECT FACES (real-time bounding box, frequent)
// ==========================
function detectFaces() {
    try {
        pctx.drawImage(video, 0, 0, procCanvas.width, procCanvas.height);
        let src = cv.imread(procCanvas);
        let gray = new cv.Mat();
        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);

        let faces = new cv.RectVector();
        faceClassifier.detectMultiScale(gray, faces, 1.4, 3);

        // Store faces for emotion prediction
        lastFaces = [];
        for (let i = 0; i < faces.size(); i++) {
            lastFaces.push(faces.get(i));
        }

        // Clear canvas overlay
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw bounding boxes with cached emotions
        const scaleX = canvas.width / procCanvas.width;
        const scaleY = canvas.height / procCanvas.height;

        for (let i = 0; i < lastFaces.length; i++) {
            let face = lastFaces[i];
            const scaledX = face.x * scaleX;
            const scaledY = face.y * scaleY;
            const scaledW = face.width * scaleX;
            const scaledH = face.height * scaleY;

            // Get cached emotion (or default to "detecting")
            const emotion = faceEmotions[i] || "detecting...";
            const satisfaction = faceEmotions[i] ? mapToSatisfaction(emotion) : "";

            // Draw bounding box
            let color = '#00FF00';
            if (emotion === "angry") color = '#FF0000';
            if (emotion === "sad") color = '#FFFF00';
            if (emotion === "happy") color = '#00FF00';

            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.strokeRect(scaledX, scaledY, scaledW, scaledH);

            // Draw emotion label
            ctx.fillStyle = color;
            ctx.font = 'bold 14px Arial';
            ctx.fillText(emotion + (satisfaction ? ' | ' + satisfaction : ''), scaledX, scaledY - 5);
        }

        src.delete();
        gray.delete();
        faces.delete();

    } catch (err) {
        console.error("Detect error:", err);
    }
}

// ==========================
// PREDICT EMOTIONS (infrequent, only every 2 seconds)
// ==========================
function predictEmotions() {
    try {
        pctx.drawImage(video, 0, 0, procCanvas.width, procCanvas.height);
        let src = cv.imread(procCanvas);

        // Process each cached face
        for (let i = 0; i < lastFaces.length; i++) {
            let face = lastFaces[i];
            if (!face) continue;

            let faceMat = src.roi(face);
            let tempCanvas = document.createElement('canvas');
            tempCanvas.width = face.width;
            tempCanvas.height = face.height;
            cv.imshow(tempCanvas, faceMat);

            // Predict emotion
            const dataArr = tf.tidy(() => {
                const pixels = tf.browser.fromPixels(tempCanvas);
                const resized = pixels.resizeNearestNeighbor([224, 224]);
                const normalized = resized.toFloat().div(255.0).expandDims();
                const pred = model.predict(normalized);
                const soft = tf.softmax(pred);
                return Array.from(soft.dataSync());
            });

            const emotion = getLabel(dataArr);
            faceEmotions[i] = emotion;
            emotionBuffer.push(emotion);

            faceMat.delete();
        }

        src.delete();

    } catch (err) {
        console.error("Predict error:", err);
    }
}

// ===== DEPRECATED: Old predict() function, no longer used =====
/*
// ==========================
// PREDICT (silent background processing, lightweight overlay)
// ==========================
function predict() {
    try {
        // ONLY process on small canvas, completely silent
        pctx.drawImage(video, 0, 0, procCanvas.width, procCanvas.height);

        let src = cv.imread(procCanvas);
        let gray = new cv.Mat();
        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);

        let faces = new cv.RectVector();
        faceClassifier.detectMultiScale(gray, faces, 1.4, 3);

        // Clear canvas overlay (transparent)
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Process each face and draw lightweight overlay only
        for (let i = 0; i < faces.size(); i++) {
            let face = faces.get(i);
            let faceMat = src.roi(face);

            // Convert to canvas for TF
            let tempCanvas = document.createElement('canvas');
            tempCanvas.width = face.width;
            tempCanvas.height = face.height;
            cv.imshow(tempCanvas, faceMat);

            // Predict emotion (with proper tensor cleanup)
            const dataArr = tf.tidy(() => {
                const pixels = tf.browser.fromPixels(tempCanvas);
                const resized = pixels.resizeNearestNeighbor([224, 224]);
                const normalized = resized.toFloat().div(255.0).expandDims();
                const pred = model.predict(normalized);
                const soft = tf.softmax(pred);
                return Array.from(soft.dataSync());
            });

            const emotion = getLabel(dataArr);
            const satisfaction = mapToSatisfaction(emotion);
            emotionBuffer.push(emotion);

            // Scale face coordinates to full canvas
            const scaleX = canvas.width / procCanvas.width;
            const scaleY = canvas.height / procCanvas.height;
            const scaledX = face.x * scaleX;
            const scaledY = face.y * scaleY;
            const scaledW = face.width * scaleX;
            const scaledH = face.height * scaleY;

            // Draw LIGHTWEIGHT overlay only (no video copy, just box + text)
            let color = '#00FF00';
            if (emotion === "angry") color = '#FF0000';
            if (emotion === "sad") color = '#FFFF00';
            if (emotion === "happy") color = '#00FF00';

            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.strokeRect(scaledX, scaledY, scaledW, scaledH);

            ctx.fillStyle = color;
            ctx.font = '16px Arial';
            ctx.fillText(emotion + ' | ' + satisfaction, scaledX, scaledY - 5);

            faceMat.delete();
        }

        src.delete();
        gray.delete();
        faces.delete();

    } catch (err) {
        console.error("Predict error:", err);
    }
}
*/

// ==========================
// LABEL
// ==========================
function getLabel(predictions) {
    const maxIndex = predictions.indexOf(Math.max(...predictions));
    return labels[maxIndex];
}

// ==========================
// MAPPING
// ==========================
function mapToSatisfaction(emotion) {
    emotion = emotion.toLowerCase().trim();
    if (emotion === "happy" || emotion === "neutral") {
        return "Puas";
    } else if (emotion === "sad" || emotion === "angry") {
        return "Tidak Puas";
    } else {
        return "Tidak Diketahui";
    }
}

function getFinalEmotion() {
    let count = {};

    emotionBuffer.forEach(e => {
        count[e] = (count[e] || 0) + 1;
    });

    let finalEmotion = Object.keys(count).reduce((a, b) =>
        count[a] > count[b] ? a : b
    );

    emotionBuffer = [];

    return finalEmotion;
}

function saveToDatabase(emotion, satisfaction) {
    fetch('/save-emotion', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            emotion: emotion,
            satisfaction: satisfaction
        })
    });
}

setInterval(() => {
    if (emotionBuffer.length > 0) {
        let finalEmotion = getFinalEmotion();
        let satisfaction = mapToSatisfaction(finalEmotion);

        saveToDatabase(finalEmotion, satisfaction);
    }
}, 10000);

