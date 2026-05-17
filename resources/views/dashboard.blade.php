@extends('layouts.navbar')

@section('content')

    <div class="container">
        <div class="camera-section">
            <div class="camera-header">
                <h2>Queue</h2>

                <div class="camera-buttons">
                    <button id="cameraBtn" onclick="startCamera()">
                        Start / Stop Camera
                    </button>
                </div>
            </div>

            <div class="video-container" style="position: relative;">

                <video id="video" autoplay playsinline muted></video>

                <canvas id="canvas"
                    style="
                        position:absolute;
                        top:0;
                        left:0;
                    ">
                </canvas>

            </div>
        </div>

        <div class="side-panel">
            <div class="card">
                <h3>WARNING</h3>

                @if($tidakPuas > 50)
                    <div class="warning-box" style="background-color: #ff4d4f; color: white;">
                        ⚠ {{ $tidakPuas }}% customers are dissatisfied
                    </div>
                @else
                    <div class="warning-box" style="background-color: #52c41a; color: white;">
                        ✅ Customer satisfaction is good ({{ $puas }}%)
                    </div>
                @endif
            </div>
            <div class="card">
                <h4 style="text-align:center; color:gray; letter-spacing:2px;">
                    Satisfaction Records Today
                </h4>

                <div class="queue-number">{{ $todayCount }}</div>
            </div>
            <div class="card">
                <h2 style="margin-bottom:20px;">Live Sentiment Mix</h2>

                @php
                    $puas = ($emotionData['happy'] ?? 0) + ($emotionData['neutral'] ?? 0);
                    $tidakPuas = ($emotionData['sad'] ?? 0) + ($emotionData['angry'] ?? 0);
                @endphp

                <div class="sentiment-item">
                    <div class="sentiment-top">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="
                                width: 10px;
                                height: 10px;
                                border-radius: 50%;
                                display: inline-block;
                                background-color: #22c55e;
                            "></span>
                            <span>Puas</span>
                        </div>
                        <span>{{ $puas }}%</span>
                    </div>

                    <div class="progress">
                        <div class="progress-bar"
                            style="width: {{ $puas }}%; background-color: #22c55e;">
                        </div>
                    </div>
                </div>

                <div class="sentiment-item">
                    <div class="sentiment-top">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="
                                width: 10px;
                                height: 10px;
                                border-radius: 50%;
                                display: inline-block;
                                background-color: #ef4444;
                            "></span>
                            <span>Tidak Puas</span>
                        </div>
                        <span>{{ $tidakPuas }}%</span>
                    </div>
                    
                    <div class="progress">
                        <div class="progress-bar"
                            style="width: {{ $tidakPuas }}%; background-color: #ef4444;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
@endsection