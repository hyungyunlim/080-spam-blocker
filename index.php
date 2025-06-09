<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>080 수신거부 자동화 시스템</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        textarea { width: 98%; height: 150px; margin-bottom: 10px; }
        input[type="text"] { width: 98%; margin-bottom: 10px; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
        #result, #recordings { margin-top: 20px; padding: 10px; border: 1px solid #ccc; background-color: #f9f9f9; white-space: pre-wrap; }
        #recordings ul { list-style: none; padding: 0; }
        #recordings li { margin-bottom: 5px; }
        #recordings a { text-decoration: none; }
    </style>
</head>
<body>
    <h1>080 수신거부 자동화 시스템</h1>
    <form id="spamForm">
        <label for="spam_message">광고 문자 내용:</label><br>
        <textarea id="spam_message" name="spam_message" required></textarea><br>

        <label for="phone_number">전화번호 (식별번호가 없는 경우):</label><br>
        <input type="text" id="phone_number" name="phone_number" placeholder="예: 01012345678"><br>
        <small style="color: #666;">💡 문자에 식별번호가 없으면 여기에 본인 전화번호를 입력하세요</small><br><br>

        <label for="dtmf_sequence">전화 연결 후 누를 버튼 (쉼표로 구분, 예: 1,2,1):</label><br>
        <input type="text" id="dtmf_sequence" name="dtmf_sequence" placeholder="예: 105623#1 (마지막 1은 자동으로 확인용으로 분리됩니다)"><br>
        <small style="color: #666;">💡 시스템이 자동으로 2단계 전송: ①식별번호# → ②확인번호1</small><br>

        <button type="submit">수신거부 전화 걸기</button>
    </form>

    <h2>처리 결과:</h2>
    <div id="result">여기에 처리 결과가 표시됩니다.</div>
    
    <hr style="margin: 30px 0;">

    <h2>녹음 파일 목록 <button onclick="loadRecordings()">새로고침</button></h2>
    <div id="recordings">
        <ul>
            <!-- 녹음 파일 목록이 여기에 동적으로 추가됩니다 -->
        </ul>
    </div>

    <script>
        // 폼 제출 이벤트 처리
        document.getElementById('spamForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            resultDiv.textContent = '처리 중... 잠시만 기다려주세요.';

            fetch('process_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                resultDiv.textContent = data;
                // 처리가 끝나면 녹음 파일 목록을 다시 불러옴
                loadRecordings(); 
            })
            .catch(error => {
                resultDiv.textContent = '오류 발생: ' + error;
            });
        });

        // 녹음 파일 목록을 불러오는 함수
        function loadRecordings() {
            const recordingsDiv = document.getElementById('recordings');
            recordingsDiv.querySelector('ul').innerHTML = '<li>로딩 중...</li>';

            fetch('get_recordings.php')
                .then(response => response.json())
                .then(files => {
                    const ul = recordingsDiv.querySelector('ul');
                    ul.innerHTML = ''; // 기존 목록 초기화
                    if (files.length === 0) {
                        ul.innerHTML = '<li>녹음된 파일이 없습니다.</li>';
                    } else {
                        files.forEach(file => {
                            const li = document.createElement('li');
                            // player.php를 통해 파일을 스트리밍하는 링크 생성
                            li.innerHTML = `<a href="player.php?file=${encodeURIComponent(file)}" target="_blank">${file}</a>`;
                            ul.appendChild(li);
                        });
                    }
                })
                .catch(error => {
                    recordingsDiv.querySelector('ul').innerHTML = `<li>목록을 불러오는 중 오류 발생: ${error}</li>`;
                });
        }

        // 페이지가 처음 로드될 때 녹음 파일 목록을 불러옴
        window.onload = loadRecordings;
    </script>
</body>
</html>
