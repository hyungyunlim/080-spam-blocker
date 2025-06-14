document.addEventListener('DOMContentLoaded',()=>{
  if(typeof IS_LOGGED!=='undefined' && !IS_LOGGED){
    const verSec=document.getElementById('verificationSection');
    const spamForm=document.getElementById('spamForm');
    let codeSent=false;
    let currentAuthPhone='';
    const notifInput=document.getElementById('notificationPhone');
    const vInput=document.getElementById('verificationCode');
    const vBtn=document.getElementById('verifyBtn');
    const vMsg=document.getElementById('verifyMsg');
    const vCountdown=document.getElementById('verifyCountdown');

    // 스팸문자 입력시 자동으로 인증 섹션 노출
    function showVerificationIfNeeded(){
      const spamContent=document.getElementById('spamContent')?.value?.trim();
      const notifPhone=notifInput?.value?.replace(/[^0-9]/g,'');
      
      if(spamContent && notifPhone && notifPhone.length>=10){
        if(!codeSent || currentAuthPhone!==notifPhone){
          // 연락처가 변경되었거나 처음 입력시 자동으로 인증번호 발송
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
      
      vMsg.textContent='인증번호를 발송중...';
      vMsg.className='verify-msg sending';
      
      fetch('api/send_code.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({phone})})
        .then(r=>r.json()).then(d=>{
          if(d.success){
            codeSent=true;
            currentAuthPhone=phone;
            verSec.style.display='block';
            verSec.scrollIntoView({behavior:'smooth'});
            vMsg.textContent=`${phone}로 인증번호를 전송했습니다.`;
            vMsg.className='verify-msg success';
            vInput.focus();
            
            // 카운트다운 시작
            if(d.expires_at){
              startCountdown(d.expires_at);
            }
          }else{
            vMsg.textContent=d.message||'전송 실패';
            vMsg.className='verify-msg error';
          }
        }).catch(err=>{
          console.error('SMS sending error:',err);
          vMsg.textContent='네트워크 오류로 전송에 실패했습니다.';
          vMsg.className='verify-msg error';
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
        vMsg.className='verify-msg error';
        vInput.focus();
        return;
      }

      vBtn.disabled=true;
      vBtn.textContent='인증중...';
      vMsg.textContent='인증번호를 확인중입니다...';
      vMsg.className='verify-msg checking';

      fetch('api/verify_code.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({phone,code})})
        .then(r=>r.json()).then(d=>{
          if(d.success){
            vMsg.textContent='✅ 인증이 완료되었습니다. 잠시만 기다려주세요...';
            vMsg.className='verify-msg success';
            // 인증 성공시 페이지 새로고침하여 로그인 상태 반영
            setTimeout(()=>window.location.reload(),1500);
          } else {
            vMsg.textContent=d.message||'인증에 실패했습니다. 다시 시도해주세요.';
            vMsg.className='verify-msg error';
            vBtn.disabled=false;
            vBtn.textContent='인증하기';
            vInput.focus();
          }
        }).catch(err=>{
          console.error('Verification error:',err);
          vMsg.textContent='네트워크 오류가 발생했습니다. 다시 시도해주세요.';
          vMsg.className='verify-msg error';
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