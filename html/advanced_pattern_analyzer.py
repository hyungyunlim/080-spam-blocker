#!/usr/bin/env python3
"""
고급 080 패턴 분석기
- Whisper large 모델 사용
- 키워드 기반 DTMF 타이밍 감지
- 자동 패턴 생성
"""

import os
import sys
import json
import whisper
import re
from datetime import datetime
import argparse

class AdvancedPatternAnalyzer:
    def __init__(self, model_size="base"):
        print("🤖 Whisper 모델 로딩 중...")
        
        # 업그레이드된 시스템 사양 체크
        import psutil
        cpu_count = psutil.cpu_count()
        memory = psutil.virtual_memory()
        available_memory_gb = memory.available / (1024**3)
        total_memory_gb = memory.total / (1024**3)
        
        print(f"🖥️  시스템 사양: {cpu_count}코어, {total_memory_gb:.1f}GB RAM")
        print(f"💾 사용 가능한 메모리: {available_memory_gb:.1f}GB")
        
        # 업그레이드된 메모리 기반 모델 선택
        optimal_model = self._select_optimal_model(model_size, available_memory_gb)
        print(f"🎯 선택된 모델: {optimal_model}")
        
        self.selected_model = optimal_model
        
        # 안전한 모델 로딩 시도
        self.model = self._load_model_safely(optimal_model)
        
        # 키워드 패턴 정의
        self.keywords = {
            # DTMF 입력 요청 키워드 (향상됨)
            'input_request': [
                '입력하세요', '입력해주세요',
                '번호를 입력', '전화번호를 입력', '식별번호를 입력',
                '고객번호를 입력', '수신거부 번호', '등록번호',
                '핸드폰 번호', '연락처', '휴대폰',
                '식별번호를 누르', '식별번호를 눌러',
                '번호를 누르', '번호를 눌러',
                '기재된 식별번호', '기재된 번호'
            ],
            
            # 확인/선택 키워드
            'confirmation': [
                '1번을 누르', '1번을 눌러', '1번 선택',
                '확인하시려면', '동의하시면', '신청하시려면',
                '원하시면', '하시려면',
                '수신거부를', '수신거부를 원하시면', '수신거부 원하시면'
            ],
            
            # 메뉴 선택 키워드
            'menu_selection': [
                '메뉴를 선택', '번호를 선택', '원하는 번호',
                '수신거부는 1번', '거부는 2번', '해지는 3번'
            ],
            
            # 완료/성공 키워드
            'completion': [
                '완료되었습니다', '처리되었습니다', '등록되었습니다',
                '신청이 완료', '수신거부 완료', '해지 완료',
                '접수되었습니다', '거부되었습니다', '정상적으로 처리되었습니다'
            ],
            
            # 오류/실패 키워드
            'error': [
                '잘못된', '올바르지 않은', '확인할 수 없', '일치하지 않',
                '다시 입력', '재입력', '오류가 발생'
            ]
        }
        
        # 시간 분석을 위한 세그먼트 저장
        self.segments = []
    
    def _select_optimal_model(self, requested_model, available_memory_gb):
        """업그레이드된 시스템 사양에 맞는 최적 모델 선택"""
        
        # 업그레이드된 시스템 활용 (4코어, 7.8GB RAM)
        if available_memory_gb >= 6.0:
            # 충분한 메모리 - 고성능 모델 사용 가능
            if requested_model in ['large-v3', 'large-v2', 'large']:
                print("⚡ 충분한 메모리: large 모델 시도하지만 medium으로 제한")
                return 'medium'  # 안정성을 위해 medium으로 제한
            elif requested_model == 'medium':
                return 'medium'
            elif requested_model in ['base', 'small']:
                return requested_model
            else:
                return 'medium'  # 기본값을 medium으로 업그레이드
                
        elif available_memory_gb >= 4.0:
            # 중간 메모리 - medium 모델 사용 가능
            if requested_model in ['large-v3', 'large-v2', 'large', 'medium']:
                print("📊 중간 메모리: medium 모델 사용")
                return 'medium'
            else:
                return requested_model if requested_model in ['base', 'small'] else 'base'
                
        elif available_memory_gb >= 2.5:
            # 제한된 메모리 - base 모델까지
            print("⚠️  제한된 메모리: base 모델 사용")
            return 'base'
            
        else:
            # 매우 제한된 메모리 - tiny 모델만
            print("⚠️  메모리 부족: tiny 모델 사용")
            return 'tiny'
    
    def _load_model_safely(self, model_size):
        """안전한 모델 로딩 with fallback"""
        fallback_order = ['medium', 'base', 'small', 'tiny']
        
        # 요청된 모델부터 시도
        try:
            print(f"⚡ {model_size} 모델 로딩 중...")
            model = whisper.load_model(model_size)
            print(f"✅ {model_size} 모델 로드 완료!")
            return model
        except Exception as e:
            print(f"⚠️  {model_size} 모델 로드 실패: {e}")
            
            # fallback 순서로 시도
            if model_size in fallback_order:
                start_idx = fallback_order.index(model_size) + 1
            else:
                start_idx = 0
                
            for fallback_model in fallback_order[start_idx:]:
                try:
                    print(f"🔄 {fallback_model} 모델로 대체 시도...")
                    model = whisper.load_model(fallback_model)
                    print(f"✅ {fallback_model} 모델 로드 완료 (백업)")
                    self.selected_model = fallback_model
                    return model
                except Exception as e2:
                    print(f"⚠️  {fallback_model} 모델도 실패: {e2}")
                    continue
            
                         # 모든 시도 실패시 에러
            raise Exception("모든 Whisper 모델 로딩 실패")
    
    def transcribe_with_timestamps(self, audio_file):
        """향상된 타임스탬프와 함께 음성 인식"""
        print(f"🎤 고성능 음성 인식 시작: {audio_file} (모델: {self.selected_model})")
        
        # 향상된 변환 옵션
        transcribe_options = {
            'language': 'ko',
            'task': 'transcribe',
            'word_timestamps': True,
            'verbose': True,
            'temperature': 0.0,  # 일관된 결과
            'compression_ratio_threshold': 2.4,
            'logprob_threshold': -1.0,
            'no_speech_threshold': 0.6,
            'initial_prompt': "080 스팸 전화, 수신거부, 식별번호, DTMF 입력"  # 컨텍스트 힌트
        }
        
        result = self.model.transcribe(audio_file, **transcribe_options)
        
        # 세그먼트 저장
        self.segments = result.get('segments', [])
        
        print(f"📝 인식 완료: {len(self.segments)}개 세그먼트")
        print(f"🎯 사용 모델: {self.selected_model}")
        return result
    
    def find_keyword_timings(self, text, segments):
        """키워드별 타이밍 찾기"""
        keyword_timings = {}
        
        for category, keywords in self.keywords.items():
            keyword_timings[category] = []
            
            for keyword in keywords:
                # 텍스트에서 키워드 찾기
                for match in re.finditer(re.escape(keyword), text, re.IGNORECASE):
                    start_pos = match.start()
                    
                    # 해당 위치의 시간 찾기
                    timing = self.find_timing_for_position(start_pos, segments)
                    if timing:
                        keyword_timings[category].append({
                            'keyword': keyword,
                            'time': timing,
                            'position': start_pos,
                            'confidence': 1.0  # 정확한 매치이므로 높은 신뢰도
                        })
        
        return keyword_timings
    
    def find_timing_for_position(self, position, segments):
        """텍스트 위치에 해당하는 시간 찾기"""
        current_pos = 0
        
        for segment in segments:
            segment_text = segment['text']
            segment_length = len(segment_text)
            
            if current_pos <= position < current_pos + segment_length:
                # 세그먼트 내 상대 위치 계산
                relative_pos = position - current_pos
                segment_duration = segment['end'] - segment['start']
                
                if segment_length > 0:
                    time_offset = (relative_pos / segment_length) * segment_duration
                    return segment['start'] + time_offset
                else:
                    return segment['start']
            
            current_pos += segment_length
        
        return None
    
    def analyze_dtmf_pattern(self, keyword_timings, transcription_text=""):
        """DTMF 패턴 분석"""
        pattern_analysis = {
            'initial_wait': 3,
            'dtmf_timing': 6,
            'dtmf_pattern': '{ID}#',
            'confirmation_wait': 4,
            'confirmation_dtmf': '1',
            'total_duration': 30,
            'confidence': 0,
            'pattern_type': 'two_step',  # default assumption
            'auto_supported': True       # can auto-unsubscribe by default
        }
        
        confidence_score = 0
        max_confidence = 100
        
        # 1. 입력 요청 시점 분석
        input_requests = keyword_timings.get('input_request', [])
        if input_requests:
            # 첫 번째 입력 요청 시점을 DTMF 타이밍으로 설정
            first_input = min(input_requests, key=lambda x: x['time'])
            pattern_analysis['dtmf_timing'] = max(1, int(first_input['time']))
            pattern_analysis['initial_wait'] = max(1, int(first_input['time']) - 1)
            confidence_score += 30
            print(f"📞 입력 요청 감지: {first_input['time']:.1f}초 - '{first_input['keyword']}'")
        
        # 2. 확인 키워드 분석
        confirmations = keyword_timings.get('confirmation', [])
        if confirmations:
            # 확인 단계가 존재하므로 기본적으로 '1' 입력으로 가정
            pattern_analysis['confirmation_dtmf'] = '1'
            confidence_score += 20
            print(f"✅ 확인 키워드 감지: {len(confirmations)}개")
        
        # 3. 메뉴 선택 패턴 분석
        menu_selections = keyword_timings.get('menu_selection', [])
        if menu_selections:
            # 메뉴 선택이 있으면 더 복잡한 패턴일 가능성
            confidence_score += 15
            print(f"📋 메뉴 선택 감지: {len(menu_selections)}개")
        
        # 4. 완료/성공 키워드 분석
        completions = keyword_timings.get('completion', [])
        if completions:
            confidence_score += 30
            print(f"🎉 완료 키워드 감지: {len(completions)}개")
            for comp in completions:
                print(f"   - {comp['keyword']} ({comp['time']:.1f}초)")
        
        # 5. 전화번호 vs 식별번호 패턴 감지 (confirm_only 는 건너뛴다)
        if pattern_analysis['pattern_type'] != 'confirm_only':
            phone_keywords = ['전화번호', '핸드폰', '휴대폰', '연락처']
            id_keywords = ['식별번호', '수신거부', '등록번호', '고객번호']

            full_text = ' '.join([kw['keyword'] for req_list in keyword_timings.values() for kw in req_list])

            if any(pk in full_text for pk in phone_keywords):
                pattern_analysis['dtmf_pattern'] = '{Phone}#'
                confidence_score += 25
                print("📱 전화번호 입력 패턴 감지")
            elif any(ik in full_text for ik in id_keywords):
                pattern_analysis['dtmf_pattern'] = '{ID}#'
                confidence_score += 25
                print("🔑 식별번호 입력 패턴 감지")
            elif '식별번호' in transcription_text or '수신거부' in transcription_text:
                pattern_analysis['dtmf_pattern'] = '{ID}#'
                confidence_score += 20
                print("🔑 텍스트에서 식별번호 패턴 감지")
        
        # 6. 타이밍 최적화 (핵심 DTMF 시점 계산)
        if input_requests and confirmations:
            input_time = min(input_requests, key=lambda x: x['time'])['time']
            confirm_time = min(confirmations, key=lambda x: x['time'])['time']
            
            if confirm_time > input_time:
                wait_time = int(confirm_time - input_time)
                pattern_analysis['confirmation_wait'] = max(2, min(wait_time, 10))
                confidence_score += 10
                print(f"⏱️  확인 대기 시간: {wait_time}초")
        
        # After confidence_score calc and before return
        # Determine pattern type based on presence of keywords
        if not input_requests and confirmations:
            pattern_analysis['pattern_type'] = 'confirm_only'
            pattern_analysis['auto_supported'] = False  # we cannot auto-unsubscribe without ID
            # Confirm-only: ID 입력이 없으므로 DTMF 패턴을 비워둔다
            pattern_analysis['dtmf_pattern'] = ''
            pattern_analysis['dtmf_timing'] = 0
            print("🔔 Confirm-only 패턴 감지 – ID 입력 없음")
        elif input_requests and not confirmations:
            pattern_analysis['pattern_type'] = 'id_only'
            # auto_supported remains True (ID only is okay)
        else:
            pattern_analysis['pattern_type'] = 'two_step'
            # keep default auto_supported True
        
        pattern_analysis['confidence'] = min(confidence_score, max_confidence)
        return pattern_analysis
    
    def generate_pattern_name(self, phone_number, keyword_timings, confidence):
        """패턴 이름 자동 생성"""
        if keyword_timings.get('input_request'):
            primary_keyword = keyword_timings['input_request'][0]['keyword']
            if '전화번호' in primary_keyword:
                return f"전화번호 입력 패턴 ({phone_number})"
            elif '식별번호' in primary_keyword:
                return f"식별번호 입력 패턴 ({phone_number})"
            else:
                return f"자동 감지 패턴 ({phone_number})"
        else:
            return f"기본 패턴 ({phone_number})"
    
    def analyze_pattern(self, audio_file, phone_number):
        """전체 패턴 분석 실행"""
        try:
            # 1. 음성 인식
            transcription_result = self.transcribe_with_timestamps(audio_file)
            full_text = transcription_result['text']
            
            print(f"📄 인식된 텍스트 ({len(full_text)}자):")
            print(f"   {full_text[:200]}...")
            
            # 2. 키워드 타이밍 분석
            keyword_timings = self.find_keyword_timings(full_text, self.segments)
            
            # 3. DTMF 패턴 분석
            pattern_data = self.analyze_dtmf_pattern(keyword_timings, full_text)
            
            # 4. 패턴 이름 생성
            pattern_name = self.generate_pattern_name(phone_number, keyword_timings, pattern_data['confidence'])
            
            # 5. 결과 구성
            result = {
                'success': True,
                'phone_number': phone_number,
                'transcription': full_text,
                'confidence': pattern_data['confidence'],
                'keywords': keyword_timings,
                'segments': self.segments,
                'pattern': {
                    'name': pattern_name,
                    'description': f"자동 분석됨 (신뢰도: {pattern_data['confidence']}%)",
                    'initial_wait': pattern_data['initial_wait'],
                    'dtmf_timing': pattern_data['dtmf_timing'],
                    'dtmf_pattern': pattern_data['dtmf_pattern'],
                    'confirmation_wait': pattern_data['confirmation_wait'],
                    'confirmation_dtmf': pattern_data['confirmation_dtmf'],
                    'total_duration': pattern_data['total_duration'],
                    'pattern_type': pattern_data.get('pattern_type', 'two_step'),
                    'auto_supported': pattern_data.get('auto_supported', True)
                },
                'analysis_time': datetime.now().isoformat(),
                'model_used': self.selected_model
            }
            
            print(f"✅ 패턴 분석 완료 (신뢰도: {pattern_data['confidence']}%)")
            return result
            
        except Exception as e:
            print(f"❌ 분석 실패: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'phone_number': phone_number
            }

def main():
    if len(sys.argv) < 4:
        print("사용법: python3 advanced_pattern_analyzer.py <audio_file> <result_file> <phone_number> [model_size]")
        print("모델 옵션: large-v3, large-v2, medium, base, small, tiny")
        print("업그레이드된 시스템: 4코어, 7.8GB RAM 활용으로 medium까지 사용 가능")
        sys.exit(1)
    
    audio_file = sys.argv[1]
    result_file = sys.argv[2] 
    phone_number = sys.argv[3]
    model_size = sys.argv[4] if len(sys.argv) > 4 else 'medium'  # 기본값을 medium으로 업그레이드
    
    if not os.path.exists(audio_file):
        print(f"❌ 오디오 파일을 찾을 수 없습니다: {audio_file}")
        sys.exit(1)
    
    print(f"🔍 업그레이드된 고급 패턴 분석 시작")
    print(f"   📞 번호: {phone_number}")
    print(f"   🎵 파일: {audio_file}")
    print(f"   🎯 요청 모델: {model_size}")
    
    analyzer = AdvancedPatternAnalyzer(model_size)
    result = analyzer.analyze_pattern(audio_file, phone_number)
    
    # 결과 저장
    with open(result_file, 'w', encoding='utf-8') as f:
        json.dump(result, f, ensure_ascii=False, indent=2)
    
    if result['success']:
        print(f"✅ 분석 결과 저장: {result_file}")
        print(f"📊 생성된 패턴:")
        print(f"   - 이름: {result['pattern']['name']}")
        print(f"   - DTMF 타이밍: {result['pattern']['dtmf_timing']}초")
        print(f"   - DTMF 패턴: {result['pattern']['dtmf_pattern']}")
        print(f"   - 신뢰도: {result['confidence']}%")
    else:
        print(f"❌ 분석 실패: {result.get('error', 'Unknown error')}")
        sys.exit(1)

if __name__ == "__main__":
    main() 