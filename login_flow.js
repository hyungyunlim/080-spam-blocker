document.addEventListener('DOMContentLoaded',()=>{
  if(typeof IS_LOGGED!=='undefined' && !IS_LOGGED){
    const verSec=document.getElementById('verificationSection');
    
    // Force hide verification section initially only for non-logged users
    if(verSec && !window.IS_LOGGED){
      verSec.classList.remove('show');
      verSec.style.display = 'none';
    }
    const spamForm=document.getElementById('spamForm');
    let codeSent=false;
    let currentAuthPhone='';
    const notifInput=document.getElementById('notificationPhone');
    const vInput=document.getElementById('verificationCode');
    const vBtn=document.getElementById('verifyBtn');
    const vMsg=document.getElementById('verifyMsg');
    const vCountdown=document.getElementById('verifyCountdown');

    // 스팸문자 입력시 자동으로 인증 섹션 노출 (mobile progressive disclosure 고려)
    function showVerificationIfNeeded(){
      const spamContent=document.getElementById('spamContent')?.value?.trim();
      const notifPhone=notifInput?.value?.replace(/[^0-9]/g,'');
      
      console.log('showVerificationIfNeeded called:', {
        spamContent: spamContent ? 'present' : 'empty',
        notifPhone,
        phoneLength: notifPhone?.length,
        windowWidth: window.innerWidth,
        codeSent,
        currentAuthPhone
      });
      
      // SMS 발송 조건 개선: 연락처만 있어도 발송 (모바일 progressive disclosure에서 스팸 내용이 먼저 입력되었을 가능성)
      if(notifPhone && notifPhone.length>=10){
        if(!codeSent || currentAuthPhone!==notifPhone){
          // 연락처가 변경되었거나 처음 입력시 자동으로 인증번호 발송
          console.log('Triggering SMS send for:', notifPhone);
          sendVerificationCode(notifPhone);
        }
      }
    }

    // 연락처 입력시 자동 인증번호 발송
    if(notifInput){
      notifInput.addEventListener('input',function(){
        const phone=this.value.replace(/[^0-9]/g,'');
        if(phone.length>=10){
          setTimeout(showVerificationIfNeeded,500); // 타이핑 완료 후 자동 발송
        }
      });
    }

    // 스팸문자 내용 입력시도 체크
    const spamContent=document.getElementById('spamContent');
    if(spamContent){
      spamContent.addEventListener('input',showVerificationIfNeeded);
    }

    function sendVerificationCode(phone){
      if(codeSent && currentAuthPhone===phone) return;
      
      console.log('Sending verification code to:', phone);
      vMsg.textContent='인증번호를 발송중...';
      vMsg.className='verify-msg sending show';
      
      fetch('api/send_code.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone })
      })
        .then(r=>r.json()).then(d=>{
          console.log('SMS API response:', d);
          if(d.success){
            codeSent=true;
            currentAuthPhone=phone;
            // Progressive disclosure compatibility - ensure verification section is shown
            if(!verSec.classList.contains('show')){
              verSec.classList.add('show');
              verSec.style.display = 'block'; // Override any inline display:none
            }
            verSec.scrollIntoView({behavior:'smooth'});
            vMsg.textContent=`${phone}로 인증번호를 전송했습니다.`;
            vMsg.className='verify-msg success show';
            vInput.focus();
            
            // 카운트다운 시작
            if(d.expires_at){
              startCountdown(d.expires_at);
            }
          }else{
            console.error('SMS sending failed:', d);
            vMsg.textContent=d.message||'전송 실패';
            vMsg.className='verify-msg error show';
          }
        }).catch(err=>{
          console.error('SMS sending error:',err);
          vMsg.textContent='네트워크 오류로 전송에 실패했습니다.';
          vMsg.className='verify-msg error show';
        });
    }

    function startCountdown(expiresAt){
      if(!vCountdown) return;
      
      const updateCountdown=()=>{
        const remaining=expiresAt-Math.floor(Date.now()/1000);
        if(remaining<=0){
          vCountdown.textContent='';
          return;
        }
        const mins=Math.floor(remaining/60);
        const secs=remaining%60;
        vCountdown.textContent=`(${mins}:${secs.toString().padStart(2,'0')})`;
      };
      
      updateCountdown();
      const interval=setInterval(()=>{
        updateCountdown();
        if(expiresAt<=Math.floor(Date.now()/1000)){
          clearInterval(interval);
        }
      },1000);
    }

    // 기존 폼 제출 이벤트 수정
    if(spamForm){
      spamForm.addEventListener('submit',function(ev){
        if(!codeSent){
          ev.preventDefault();
          const phone=notifInput.value.replace(/[^0-9]/g,'');
          if(!phone){
            alert('알림받을 연락처를 입력하세요');
            notifInput.focus();
            return;
          }
          sendVerificationCode(phone);
        } else if(!IS_LOGGED){
          // 인증번호가 발송되었지만 아직 로그인되지 않은 경우
          ev.preventDefault();
          if(!vInput.value.trim()){
            alert('인증번호를 입력하세요');
            vInput.focus();
            return;
          }
          // 인증 처리
          verifyCode();
        }
      });
    }

    function verifyCode(){
      const phone=notifInput.value.replace(/[^0-9]/g,'');
      const code=vInput.value.trim();
      
      if(code.length!==6){
        vMsg.textContent='6자리 인증번호를 정확히 입력하세요';
        vMsg.className='verify-msg error show';
        vInput.focus();
        return;
      }

      // 인증 전에 폼 데이터를 세션 스토리지에 저장
      const formData = {
        spam_content: document.getElementById('spamContent')?.value || '',
        notification_phone: phone,
        phone_number: document.getElementById('phoneNumber')?.value || '',
        timestamp: Date.now()
      };
      sessionStorage.setItem('pending_spam_form', JSON.stringify(formData));
      console.log('Pending form data saved:', formData);

      vBtn.disabled=true;
      vBtn.textContent='인증중...';
      vMsg.textContent='인증번호를 확인중입니다...';
      vMsg.className='verify-msg checking show';

      fetch('api/verify_code.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone, code })
      })
        .then(r=>r.json()).then(d=>{
          if(d.success){
            vMsg.textContent='✅ 인증이 완료되었습니다. 잠시만 기다려주세요...';
            vMsg.className='verify-msg success show';
            
            // Check if redirect is requested by server
            if(d.redirect){
              // Server requested redirect, reload to logged-in state
              setTimeout(()=>{
                console.log('Authentication successful, reloading page...', {
                  logged_in: d.logged_in,
                  user_phone: d.user_phone
                });
                
                // Keep pending form data for processing after login
                // (will be processed by pending_form_processor.js after reload)
                
                // Force complete page reload with cache busting
                try {
                  const currentUrl = new URL(window.location);
                  currentUrl.searchParams.set('auth_complete', Date.now());
                  currentUrl.searchParams.set('_', Date.now()); // Additional cache buster
                  
                  console.log('Redirecting to:', currentUrl.toString());
                  
                  // Use replace to avoid adding to history
                  window.location.replace(currentUrl.toString());
                } catch(e) {
                  console.error('Redirect failed, trying simple reload:', e);
                  // Fallback: simple reload
                  window.location.reload(true);
                }
              }, 1500);
            } else {
              // Fallback: standard reload with cache busting
              setTimeout(()=>{
                window.location.reload(true);
              },1500);
            }
          } else {
            vMsg.textContent=d.message||'인증에 실패했습니다. 다시 시도해주세요.';
            vMsg.className='verify-msg error show';
            vBtn.disabled=false;
            vBtn.textContent='인증하기';
            vInput.focus();
          }
        }).catch(err=>{
          console.error('Verification error:',err);
          vMsg.textContent='네트워크 오류가 발생했습니다. 다시 시도해주세요.';
          vMsg.className='verify-msg error show';
          vBtn.disabled=false;
          vBtn.textContent='인증하기';
        });
    }

    if(vBtn){
      vBtn.addEventListener('click',verifyCode);
    }

    // 인증번호 엔터키 처리
    if(vInput){
      vInput.addEventListener('keypress',function(e){
        if(e.key==='Enter'){
          e.preventDefault();
          verifyCode();
        }
      });
    }
  }
}); 