<?php
$conn = new mysqli("localhost", "root", "", "emotion_db");
// Check connection


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Just get the questions
$sql = "SELECT question FROM questions LIMIT 10";
$result = $conn->query($sql);
$questions = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $questions[] = $row['question']; // ONLY the text, not full object
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voice Emotion Detection FAQ</title>
    <link rel="stylesheet" href="survey_style.css">
</head>
<body>
    <div class="container">
        <h1>Voice Emotion Detection</h1>
        
        <div class="progress-container">
            <div class="question-counter">Question <span id="currentQuestionNum">1</span> of <span id="totalQuestions"><?php echo count($questions); ?></span></div>
            <div class="progress">
                <div class="progress-bar"></div>
            </div>
        </div>
        
        <div id="questionBox">Loading question...</div>
        
        <div class="settings">
            <h3>API Settings</h3>
            <label for="apiUrl">API URL:</label>
            <input type="text" id="apiUrl" class="api-url-input" value="http://localhost:8000/record">
        </div>
        
        <div class="controls">
            <button id="recordButton" class="control-button"><i></i> Record</button>
            <button id="stopButton" class="control-button" disabled>Stop</button>
            <button id="analyzeButton" class="control-button" disabled> Analyze</button>
            <button id="nextQuestionBtn" class="control-button" disabled>Next Question</button>
        </div>
        
        <div id="statusIndicator">Click Record to start recording your answer</div>
        
        <div class="audio-container" id="audioContainer">
            <h3>Recorded Audio</h3>
            <audio id="audioPlayer" controls></audio>
        </div>
        
        <div class="result-container" id="resultContainer">
            <h3>Emotion Analysis Results</h3>
            
            <div class="overall-summary" id="overallSummary">
                <div>
                    <strong>Overall Emotion:</strong> 
                    <span id="overallEmotion" class="emotion-display-inline">Processing...</span>
                </div>
                <div class="recording-info">
                    <span id="totalDuration">0</span> seconds total duration
                </div>
            </div>
            
            <h4>Emotion Analysis by Chunk</h4>
            <table class="chunksTable" id="chunksTable">
                <thead>
                    <tr>
                        <th>Chunk #</th>
                        <th>Time Range (sec)</th>
                        <th>Predicted Emotion</th>
                        <th>Probabilities</th>
                    </tr>
                </thead>
                <tbody id="chunksTableBody">
                    <!-- Results will be inserted here -->
                </tbody>
            </table>
        </div>
        
        <div id="finalMessage" class="final-message">
            Thank you for completing all questions! Click below to see your results.
        </div>
        
        <button id="homeButton" class="home-button">Go to Results Page</button>
    </div>
    
    <script>
        // Questions from PHP
        let questions = <?php echo json_encode($questions); ?>;
        if (questions.length === 0) {
            questions = ["How are you feeling today?"]; // Default question if database is empty
        }
        
        // Global variables
        let mediaRecorder;
        let audioChunks = [];
        let audioBlob;
        let wavBlob;
        let currentQuestion = 0;
        let questionResults = [];
        let isRecording = false;
        
        // DOM elements
        const recordButton = document.getElementById('recordButton');
        const stopButton = document.getElementById('stopButton');
        const analyzeButton = document.getElementById('analyzeButton');
        const nextQuestionBtn = document.getElementById('nextQuestionBtn');
        const statusIndicator = document.getElementById('statusIndicator');
        const audioPlayer = document.getElementById('audioPlayer');
        const audioContainer = document.getElementById('audioContainer');
        const resultContainer = document.getElementById('resultContainer');
        const overallEmotion = document.getElementById('overallEmotion');
        const chunksTableBody = document.getElementById('chunksTableBody');
        const apiUrlInput = document.getElementById('apiUrl');
        const totalDuration = document.getElementById('totalDuration');
        const progressBar = document.querySelector('.progress-bar');
        const finalMessage = document.getElementById('finalMessage');
        const homeButton = document.getElementById('homeButton');
        const questionBox = document.getElementById('questionBox');
        const currentQuestionNum = document.getElementById('currentQuestionNum');
        const totalQuestions = document.getElementById('totalQuestions');
        
        // Initial setup
        updateQuestionDisplay();
        updateProgress();
        
        // Event listeners
        recordButton.addEventListener('click', startRecording);
        stopButton.addEventListener('click', stopRecording);
        analyzeButton.addEventListener('click', analyzeEmotion);
        nextQuestionBtn.addEventListener('click', nextQuestion);
        homeButton.addEventListener('click', () => {
            window.location.href = 'home.php'; // Replace with your home page URL
        });
        
        // Functions
        function updateQuestionDisplay() {
            if (currentQuestion < questions.length) {
                questionBox.textContent = questions[currentQuestion];
                currentQuestionNum.textContent = currentQuestion + 1;
            }
        }
        
        function updateProgress() {
            const progress = ((currentQuestion) / questions.length) * 100;
            progressBar.style.width = `${progress}%`;
        }
        
        async function startRecording() {
            if (isRecording) return;
            
            try {
                // Request access to the microphone
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    audio: {
                        sampleRate: 41000,
                        channelCount: 1
                    } 
                });
                
                // Create a MediaRecorder with specific MIME type
                const options = { mimeType: 'audio/webm' };
                mediaRecorder = new MediaRecorder(stream, options);
                
                mediaRecorder.ondataavailable = (event) => {
                    audioChunks.push(event.data);
                };
                
                mediaRecorder.onstop = async () => {
                    // Create blob from recorded chunks
                    audioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType });
                    
                    // Create URL for playback
                    const audioUrl = URL.createObjectURL(audioBlob);
                    audioPlayer.src = audioUrl;
                    audioContainer.classList.add('active');
                    
                    // Prepare audio for API
                    wavBlob = audioBlob;
                    
                    statusIndicator.textContent = 'Recording saved! Click "Analyze" to process your response.';
                    
                    // Enable analyze button
                    analyzeButton.disabled = false;
                    
                    // Update recording state
                    isRecording = false;
                };
                
                audioChunks = [];
                mediaRecorder.start();
                isRecording = true;
                
                // Update UI
                recordButton.classList.add('recording');
                recordButton.disabled = true;
                stopButton.disabled = false;
                analyzeButton.disabled = true;
                nextQuestionBtn.disabled = true;
                statusIndicator.textContent = 'Recording... Speak now.';
                
                // Reset result container if it was previously shown
                resultContainer.classList.remove('active');
                
            } catch (error) {
                console.error('Error accessing microphone:', error);
                statusIndicator.textContent = 'Error: Could not access microphone. Please check permissions.';
                isRecording = false;
            }
        }
        
        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                
                // Stop all tracks on the stream
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
                
                recordButton.classList.remove('recording');
                recordButton.disabled = false;
                stopButton.disabled = true;
                
                statusIndicator.textContent = 'Saving recording...';
            }
        }
        
        async function analyzeEmotion() {
            if (!wavBlob) {
                statusIndicator.textContent = 'Error: No audio recording found.';
                return;
            }
            
            // Disable analyze button during processing
            analyzeButton.disabled = true;
            statusIndicator.textContent = 'Analyzing emotions in your response...';
            
            try {
                // Get API URL from input field
                const apiUrl = apiUrlInput.value.trim();
                
                // Prepare form data for the API request
                const formData = new FormData();
                formData.append('file', wavBlob, 'recording.wav');
                formData.append('question_id', currentQuestion + 1);
                formData.append('question_text', questions[currentQuestion]);
                
                // Send the audio to the API
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formData,
                });
                
                if (!response.ok) {
                    throw new Error(`Server responded with status: ${response.status}`);
                }
                
                // Parse the JSON response
                const result = await response.json();
                
                // Store the result for this question
                questionResults.push({
                    question: questions[currentQuestion],
                    emotion_result: result
                });
                
                // Display the results
                displayResults(result);
                
                statusIndicator.textContent = 'Analysis complete! Click "Next Question" to continue.';
                resultContainer.classList.add('active');
                
                // Enable next question button
                nextQuestionBtn.disabled = false;
                
            } catch (error) {
                console.error('Error analyzing emotion:', error);
                statusIndicator.textContent = 'Error analyzing emotion: ' + error.message;
                overallEmotion.textContent = 'Analysis failed';
                
                // Re-enable analyze button to try again
                analyzeButton.disabled = false;
                
                // Still allow to continue to next question
                nextQuestionBtn.disabled = false;
            }
        }
        
        function getEmotionColorClass(emotion) {
            const emotionLower = emotion.toLowerCase();
            if (emotionLower.includes('happy')) return 'happy';
            if (emotionLower.includes('sad')) return 'sad';
            if (emotionLower.includes('angry')) return 'angry';
            if (emotionLower.includes('neutral')) return 'neutral';
            if (emotionLower.includes('anxious')) return 'anxious';
            return '';
        }
        
        function displayResults(results) {
            // Calculate total duration
            const totalDurationValue = results.chunks.reduce((total, chunk) => {
                return total + (chunk.end - chunk.start);
            }, 0).toFixed(1);
            
            // Display overall emotion
            overallEmotion.textContent = results.dominant_emotion;
            overallEmotion.className = 'emotion-display-inline ' + getEmotionColorClass(results.dominant_emotion);
            
            // Display total duration
            totalDuration.textContent = totalDurationValue;
            
            // Clear previous results
            chunksTableBody.innerHTML = '';
            
            // Display chunks in the table
            results.chunks.forEach((chunk, index) => {
                const row = document.createElement('tr');
                
                // Chunk ID cell
                const chunkIdCell = document.createElement('td');
                chunkIdCell.textContent = index + 1;
                row.appendChild(chunkIdCell);
                
                // Time range cell
                const timeRangeCell = document.createElement('td');
                timeRangeCell.textContent = `${chunk.start} - ${chunk.end}`;
                row.appendChild(timeRangeCell);
                
                // Emotion cell
                const emotionCell = document.createElement('td');
                emotionCell.className = 'emotion-cell ' + getEmotionColorClass(chunk.emotion);
                emotionCell.textContent = chunk.emotion;
                row.appendChild(emotionCell);
                
                // Probabilities cell
                const probabilitiesCell = document.createElement('td');
                probabilitiesCell.className = 'probabilities-cell';
                
                const probabilitiesContainer = document.createElement('div');
                probabilitiesContainer.className = 'probabilities-container';
                
                // Sort emotions by probability (descending)
                const sortedEmotions = Object.entries(chunk.confidence_scores)
                    .sort((a, b) => b[1] - a[1]);
                
                for (const [emotion, probability] of sortedEmotions) {
                    const percentage = Math.round(probability * 100);
                    
                    const probabilityItem = document.createElement('div');
                    probabilityItem.className = 'probability-item';
                    probabilityItem.innerHTML = `
                        <span>${emotion}: ${percentage}%</span>
                    `;
                    
                    const progressMini = document.createElement('div');
                    progressMini.className = 'progress-mini';
                    
                    const progressFillMini = document.createElement('div');
                    progressFillMini.className = 'progress-fill-mini';
                    progressFillMini.style.width = `${percentage}%`;
                    
                    // Color the progress bar based on emotion
                    const colorClass = getEmotionColorClass(emotion);
                    if (colorClass === 'happy') progressFillMini.style.backgroundColor = '#27ae60';
                    else if (colorClass === 'sad') progressFillMini.style.backgroundColor = '#3498db';
                    else if (colorClass === 'angry') progressFillMini.style.backgroundColor = '#e74c3c';
                    else if (colorClass === 'neutral') progressFillMini.style.backgroundColor = '#7f8c8d';
                    else if (colorClass === 'anxious') progressFillMini.style.backgroundColor = '#f1c40f';
                    else progressFillMini.style.backgroundColor = '#95a5a6';
                    
                    progressMini.appendChild(progressFillMini);
                    probabilityItem.appendChild(progressMini);
                    
                    probabilitiesContainer.appendChild(probabilityItem);
                }
                
                probabilitiesCell.appendChild(probabilitiesContainer);
                row.appendChild(probabilitiesCell);
                
                chunksTableBody.appendChild(row);
            });
        }
        
        function nextQuestion() {
            // Reset UI elements
            audioContainer.classList.remove('active');
            resultContainer.classList.remove('active');
            
            // Reset buttons
            recordButton.disabled = false;
            stopButton.disabled = true;
            analyzeButton.disabled = true;
            nextQuestionBtn.disabled = true;
            
            // Move to next question
            currentQuestion++;
            
            // Save results to session storage for the results page
            sessionStorage.setItem('questionResults', JSON.stringify(questionResults));
            
            if (currentQuestion < questions.length) {
                // Display next question
                updateQuestionDisplay();
                updateProgress();
                statusIndicator.textContent = 'Click "Record" to start recording your answer';
            } else {
                // All questions completed
                questionBox.style.display = 'none';
                recordButton.style.display = 'none';
                stopButton.style.display = 'none';
                analyzeButton.style.display = 'none';
                nextQuestionBtn.style.display = 'none';
                statusIndicator.style.display = 'none';
                finalMessage.classList.add('active');
                homeButton.classList.add('active');
                updateProgress(); // Update to 100%
            }
        }
    </script>
</body>
</html>