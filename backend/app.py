
from fastapi import FastAPI, UploadFile, File
from fastapi.middleware.cors import CORSMiddleware
import numpy as np
import librosa
import tensorflow as tf
from sklearn.preprocessing import LabelEncoder
import tempfile
import os
import shutil
import uuid
import soundfile as sf

app = FastAPI()

# Add CORS middleware to allow cross-origin requests
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, replace with your specific frontend URL
    allow_credentials=True,
    allow_methods=["GET", "POST", "OPTIONS"],
    allow_headers=["*"],
)

# Load the trained model
# Update this path to where your model is stored
MODEL_PATH = "best_model.h5"
rf_model = tf.keras.models.load_model(MODEL_PATH)

# Define the label mapping (ensure this matches your training setup)
label_mapping = {
    '01': 'Angry',
    '02': 'Sad',
    '03': 'Happy',
    '04': 'Neutral',
    '05': 'Fear',
}

SR = 41000  # Use 41 kHz sample rate
MAX_LENGTH = 240  # Updated for 41 kHz, 3-second audio
HOP_LENGTH = 512  # Default librosa value

# Load trained labels to fit the LabelEncoder
# Update this path to where your labels are stored
LABELS_PATH = "C:\\xampp\\htdocs\\Ecommerce-Store\\emotion detection system\\backend\\Selected_Audios_labels_41k.npy"
y_loaded = np.load(LABELS_PATH)
le = LabelEncoder()
le.fit(y_loaded)

def extract_features(audio_data, sr):
    """Extract MFCC features from audio data"""
    if sr != SR:
        audio_data = librosa.resample(audio_data, orig_sr=sr, target_sr=SR)
    
    mfccs = librosa.feature.mfcc(y=audio_data, sr=SR, n_mfcc=40, hop_length=HOP_LENGTH)
    
    # Pad/truncate to MAX_LENGTH
    if mfccs.shape[1] > MAX_LENGTH:
        mfccs = mfccs[:, :MAX_LENGTH]
    else:
        pad_width = MAX_LENGTH - mfccs.shape[1]
        mfccs = np.pad(mfccs, pad_width=((0,0), (0, pad_width)), mode='constant')
    
    return mfccs.T

def predict_from_audio_data(audio_data, sr):
    """Predict emotion from audio data"""
    mfcc_sequence = extract_features(audio_data, sr)
    mfcc_batch = np.expand_dims(mfcc_sequence, axis=0)
    
    # Predict probabilities
    predictions = rf_model.predict(mfcc_batch)
    
    # Get confidence scores for each emotion
    confidence_scores = {label_mapping[le.classes_[i]]: float(predictions[0][i]) for i in range(len(le.classes_))}
    
    # Get the predicted class index
    predicted_class_idx = np.argmax(predictions[0])
    
    # Map the numerical label to the emotion class
    predicted_label = le.classes_[predicted_class_idx]
    predicted_emotion = label_mapping[predicted_label]
    
    return {
        "emotion": predicted_emotion,
        "confidence_scores": confidence_scores
    }

def chunk_audio(audio_data, sr, chunk_duration=3.0, overlap=1.0):
    """Split audio into overlapping chunks of specified duration"""
    chunk_length = int(chunk_duration * sr)
    overlap_length = int(overlap * sr)
    step = chunk_length - overlap_length
    
    # Calculate total number of chunks
    audio_length = len(audio_data)
    
    chunks = []
    timestamps = []
    
    # Handle short audio (less than chunk_duration)
    if audio_length < chunk_length:
        # Pad with silence if needed
        pad_length = chunk_length - audio_length
        padded_audio = np.pad(audio_data, (0, pad_length), mode='constant')
        chunks.append(padded_audio)
        timestamps.append((0, chunk_duration))
        return chunks, timestamps
    
    # Process longer audio with overlapping chunks
    for i in range(0, audio_length - overlap_length, step):
        end = i + chunk_length
        if end > audio_length:
            # Pad the last chunk if needed
            chunk = np.pad(audio_data[i:], (0, end - audio_length), mode='constant')
        else:
            chunk = audio_data[i:end]
        
        chunks.append(chunk)
        start_time = i / sr
        end_time = min((i + chunk_length) / sr, audio_length / sr)
        timestamps.append((start_time, end_time))
    
    return chunks, timestamps

@app.post("/predict")
async def predict_emotion(file: UploadFile = File(...)):
    """Process uploaded audio file and predict emotion"""
    # Create a temporary file to store the uploaded audio
    temp_dir = tempfile.mkdtemp()
    try:
        temp_path = os.path.join(temp_dir, f"{uuid.uuid4()}.wav")
        with open(temp_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)
        
        # Load the audio file
        audio_data, sr = librosa.load(temp_path, sr=None)  # Load with original sampling rate
        
        # Check if audio is longer than 3 seconds
        duration = len(audio_data) / sr
        
        if duration <= 3.0:
            # For short audio, just run single prediction
            result = predict_from_audio_data(audio_data, sr)
            return {
                "chunks": [
                    {
                        "start": 0,
                        "end": duration,
                        "emotion": result["emotion"],
                        "confidence_scores": result["confidence_scores"]
                    }
                ],
                "dominant_emotion": result["emotion"]
            }
        else:
            # For longer audio, break into chunks
            chunks, timestamps = chunk_audio(audio_data, sr)
            
            results = []
            # Process each chunk
            for i, (chunk, (start, end)) in enumerate(zip(chunks, timestamps)):
                result = predict_from_audio_data(chunk, sr)
                results.append({
                    "start": round(start, 2),
                    "end": round(end, 2),
                    "emotion": result["emotion"],
                    "confidence_scores": result["confidence_scores"]
                })
            
            # Determine dominant emotion (most frequent)
            emotions = [r["emotion"] for r in results]
            from collections import Counter
            emotion_counts = Counter(emotions)
            dominant_emotion = emotion_counts.most_common(1)[0][0]
            
            return {
                "chunks": results,
                "dominant_emotion": dominant_emotion
            }
            
    finally:
        # Clean up temporary files
        shutil.rmtree(temp_dir)

@app.post("/record")
async def process_recorded_audio(file: UploadFile = File(...)):
    """Process recorded audio from the frontend"""
    return await predict_emotion(file)

@app.get("/")
def read_root():
    return {"message": "Speech Emotion Recognition API"}

#uvicorn backend.app:app --host 0.0.0.0 --port 8000 --reload