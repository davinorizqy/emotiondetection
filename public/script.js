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

            startPredictionLoop();

            console.log("Camera ON ✅");
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
    setInterval(() => {
        if (model && cvReady && faceClassifier && video.readyState === 4) {
            predict();
        }
    }, 500);
}

// ==========================
// PREDICT
// ==========================
function predict() {
    try {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        let src = cv.imread(canvas);
        let gray = new cv.Mat();

        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);

        let faces = new cv.RectVector();
        faceClassifier.detectMultiScale(gray, faces, 1.2, 2);

        console.log("faces:", faces.size());

        // 🔥 LOOP SEMUA WAJAH
        for (let i = 0; i < faces.size(); i++) {
            let face = faces.get(i);

            let p1 = new cv.Point(face.x, face.y);
            let p2 = new cv.Point(face.x + face.width, face.y + face.height);

            // ==========================
            // CROP WAJAH
            // ==========================
            let faceMat = src.roi(face);

            let tempCanvas = document.createElement('canvas');
            tempCanvas.width = face.width;
            tempCanvas.height = face.height;

            cv.imshow(tempCanvas, faceMat);

            // ==========================
            // PREDIKSI EMOSI
            // ==========================
            let tensor = tf.browser.fromPixels(tempCanvas)
                .resizeNearestNeighbor([224, 224])
                .toFloat()
                .div(255.0)
                .expandDims();

            const prediction = model.predict(tensor);
            const data = tf.softmax(prediction).dataSync();

            const emotion = getLabel(Array.from(data));
            const satisfaction = mapToSatisfaction(emotion);

            // simpan ke buffer (buat 3 detik)
            emotionBuffer.push(emotion);

            // ==========================
            // GAMBAR KOTAK + TEXT
            // ==========================
            let org = new cv.Point(face.x, face.y - 10);
            let color = [0, 255, 0, 255]; // default

            if (emotion === "angry") color = [255, 0, 0, 255];
            if (emotion === "sad") color = [255, 255, 0, 255];
            if (emotion === "happy") color = [0, 255, 0, 255];

            cv.rectangle(src, p1, p2, color, 2);
            let text = emotion + " | " + satisfaction;

            cv.putText(
                src,
                text,
                org,
                cv.FONT_HERSHEY_SIMPLEX,
                0.6,
                [0, 255, 0, 255],
                2
            );

            faceMat.delete();
        }

        cv.imshow(canvas, src);

        src.delete();
        gray.delete();
        faces.delete();

    } catch (err) {
        console.error("Predict error:", err);
    }
}

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

