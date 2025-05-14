// DOM Elements
const recordButton = document.getElementById('recordButton');
const fileUpload = document.getElementById('fileUpload');
const fileInfo = document.getElementById('fileInfo');
const audioPreview = document.getElementById('audioPreview');
const audioElement = document.getElementById('audioElement');
const audioLabel = document.getElementById('audioLabel');
const playButton = document.getElementById('playButton');
const processButton = document.getElementById('processButton');
const errorMessage = document.getElementById('errorMessage');
const resultsCard = document.getElementById('resultsCard');
const dominantEmotion = document.getElementById('dominantEmotion');
const resultsBody = document.getElementById('resultsBody');
const loadingOverlay = document.getElementById('loadingOverlay');

// API endpoint - change this to your actual backend URL
const API_URL = 'http://localhost:8000';

// Global variables
let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;
let recordedBlob = null;
let uploadedFile = null;

// Emotion colors mapping
const emotionClasses = {
    'Angry': 'emotion-angry',
    'Sad': 'emotion-sad',
    'Happy': 'emotion-happy',
    'Neutral': 'emotion-neutral',
    'Fear': 'emotion-fear'
};

const barClasses = {
    'Angry': 'bar-angry',
    'Sad': 'bar-sad',
    'Happy': 'bar-happy',
    'Neutral': 'bar-neutral',
    'Fear': 'bar-fear'
};

// Event Listeners
recordButton.addEventListener('click', toggleRecording);
fileUpload.addEventListener('change', handleFileUpload);
processButton.addEventListener('click', processAudio);
playButton.addEventListener('click', toggleAudioPlayback);
audioElement.addEventListener('play', () => updatePlayButton(true));
audioElement.addEventListener('pause', () => updatePlayButton(false));
audioElement.addEventListener('ended', () => updatePlayButton(false));

// Functions
function toggleRecording() {
    if (isRecording) {
        stopRecording();
    } else {
        startRecording();
    }
}

async function startRecording() {
    try {
        audioChunks = [];
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        
        mediaRecorder = new MediaRecorder(stream);
        
        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                audioChunks.push(event.data);
            }
        };
        
        mediaRecorder.onstop = () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
            const audioUrl = URL.createObjectURL(audioBlob);
            
            audioElement.src = audioUrl;
            audioPreview.classList.remove('hidden');
            audioLabel.textContent = 'Recorded Audio';
            
            recordedBlob = audioBlob;
            uploadedFile = null; // Clear any previously uploaded file
            
            processButton.disabled = false;
        };
        
        mediaRecorder.start();
        isRecording = true;
        recordButton.innerHTML = '<i class="fas fa-stop"></i> Stop Recording';
        recordButton.classList.add('recording');
        hideError();
        
    } catch (err) {
        console.error('Error starting recording:', err);
        showError('Could not access microphone. Please check permissions.');
    }
}

function stopRecording() {
    if (mediaRecorder && isRecording) {
        mediaRecorder.stop();
        
        // Stop all audio tracks
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
        
        isRecording = false;
        recordButton.innerHTML = '<i class="fas fa-microphone"></i> Start Recording';
        recordButton.classList.remove('recording');
    }
}

function handleFileUpload(e) {
    const file = e.target.files[0];
    if (file) {
        // Check if it's an audio file
        if (!file.type.startsWith('audio/')) {
            showError('Please upload an audio file.');
            return;
        }
        
        uploadedFile = file;
        const audioUrl = URL.createObjectURL(file);
        audioElement.src = audioUrl;
        audioPreview.classList.remove('hidden');
        audioLabel.textContent = file.name;
        
        fileInfo.textContent = `Selected: ${file.name}`;
        recordedBlob = null; // Clear any previously recorded audio
        processButton.disabled = false;
        hideError();
    }
}

function toggleAudioPlayback() {
    if (audioElement.paused) {
        audioElement.play();
    } else {
        audioElement.pause();
    }
}

function updatePlayButton(isPlaying) {
    playButton.innerHTML = isPlaying ? 
        '<i class="fas fa-pause"></i>' : 
        '<i class="fas fa-play"></i>';
}

async function processAudio() {
    showLoading();
    hideError();
    try {
        const formData = new FormData();
        
        if (recordedBlob) {
            formData.append('file', recordedBlob, 'recorded_audio.wav');
            const response = await fetch(`${API_URL}/record`, {
                method: 'POST',
                body: formData,
            });
            
            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}`);
            }
            
            const data = await response.json();
            displayResults(data);
        } else if (uploadedFile) {
            formData.append('file', uploadedFile);
            const response = await fetch(`${API_URL}/predict`, {
                method: 'POST',
                body: formData,
            });
            
            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}`);
            }
            
            const data = await response.json();
            displayResults(data);
        } else {
            showError('Please record or upload an audio file first.');
        }
    } catch (err) {
        console.error('Error processing audio:', err);
        showError(`Error: ${err.message || 'Could not process audio'}`);
    } finally {
        hideLoading();
    }
}

function displayResults(results) {
    // Set dominant emotion
    dominantEmotion.textContent = results.dominant_emotion;
    dominantEmotion.className = 'emotion-tag';
    dominantEmotion.classList.add(emotionClasses[results.dominant_emotion] || 'emotion-neutral');
    console.log(dominantEmotion.textContent);
    console.log(results.dominant_emotion);
    // Clear previous results
    resultsBody.innerHTML = '';
    
    // Add rows for each chunk
    results.chunks.forEach((chunk, index) => {
        const row = document.createElement('tr');
        
        // Segment number
        const segmentCell = document.createElement('td');
        segmentCell.textContent = index + 1;
        row.appendChild(segmentCell);
        
        // Time range
        const timeCell = document.createElement('td');
        timeCell.textContent = `${chunk.start} - ${chunk.end}`;
        row.appendChild(timeCell);
        
        // Emotion
        const emotionCell = document.createElement('td');
        const emotionSpan = document.createElement('span');
        emotionSpan.textContent = chunk.emotion;
        emotionSpan.className = 'emotion-tag';
        emotionSpan.classList.add(emotionClasses[chunk.emotion] || 'emotion-neutral');
        emotionSpan.style.fontSize = '14px';
        emotionSpan.style.padding = '4px 10px';
        emotionCell.appendChild(emotionSpan);
        row.appendChild(emotionCell);
        
        // Confidence graphs
        const confidenceCell = document.createElement('td');
        const graphContainer = document.createElement('div');
        graphContainer.className = 'confidence-graph';
        
        if (chunk.confidence_scores) {
            Object.entries(chunk.confidence_scores).forEach(([emotion, score]) => {
                const barDiv = document.createElement('div');
                barDiv.className = 'confidence-bar';
                
                const labelSpan = document.createElement('span');
                labelSpan.className = 'emotion-label';
                labelSpan.textContent = emotion;
                
                const barContainer = document.createElement('div');
                barContainer.className = 'bar-container';
                
                const bar = document.createElement('div');
                bar.className = 'bar';
                bar.classList.add(barClasses[emotion] || 'bar-neutral');
                bar.style.width = `${Math.round(score * 100)}%`;
                
                const percentSpan = document.createElement('span');
                percentSpan.className = 'percentage';
                percentSpan.textContent = `${Math.round(score * 100)}%`;
                
                barContainer.appendChild(bar);
                barDiv.appendChild(labelSpan);
                barDiv.appendChild(barContainer);
                barDiv.appendChild(percentSpan);
                
                graphContainer.appendChild(barDiv);
            });
        }
        
        confidenceCell.appendChild(graphContainer);
        row.appendChild(confidenceCell);
        
        resultsBody.appendChild(row);
    });
    
    // Show results card
    resultsCard.classList.remove('hidden');
    
    // Scroll to results
    resultsCard.scrollIntoView({ behavior: 'smooth' });
}

function showError(message) {
    errorMessage.textContent = message;
    errorMessage.classList.remove('hidden');
}

function hideError() {
    errorMessage.classList.add('hidden');
}

function showLoading() {
    loadingOverlay.classList.remove('hidden');
}

function hideLoading() {
    loadingOverlay.classList.add('hidden');
}