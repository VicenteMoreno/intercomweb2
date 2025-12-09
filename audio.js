// audio.js - cliente WebRTC + señalización WebSocket integrado con salas y usuarios por IP.
// Preserva la UI original (botones icombtn con data-ip) y comportamiento press-to-talk.

(function(){
  'use strict';

  const MY_IP = window.__miIP || '';
  const MY_USER = window.__miUser || '';
  const SALA = window.__salaActivo || '';
  const SIGNALING_URL = (window.INTERCOM_CONFIG && window.INTERCOM_CONFIG.signalingUrl) ? window.INTERCOM_CONFIG.signalingUrl : (location.protocol === 'https:' ? 'wss' : 'ws') + '://' + location.host + '/intercom/ws';
  const TOKEN = window.INTERCOM_TOKEN || '';

  let ws = null;
  let localStream = null;
  const peers = {}; // peers[ip] = { pc, audioEl }

  function log(){ console.log.apply(console, ['[audio.js]'].concat(Array.from(arguments))); }
  function appendLog(msg){ try{ const el=document.getElementById('log'); if(el){ el.value += new Date().toISOString() + ' ' + msg + '\n'; el.scrollTop = el.scrollHeight; } }catch(e){} }

  async function iniciarStream(){
    if (localStream) return localStream;
    const audioInId = localStorage.getItem('audioIn');
    try{
      const constraints = audioInId ? { audio: { deviceId: { exact: audioInId } }, video: false } : { audio: true, video: false };
      localStream = await navigator.mediaDevices.getUserMedia(constraints);
      log('Local stream ok, tracks:', localStream.getAudioTracks().length);
      appendLog('Mic OK');
      return localStream;
    }catch(e){
      log('getUserMedia error', e);
      appendLog('Mic ERROR: ' + e.message);
      throw e;
    }
  }

  function ensureAudioPlayback(audioEl){
    audioEl.autoplay = true;
    audioEl.controls = true;
    try {
      const ac = window.audioContext || (window.audioContext = new (window.AudioContext || window.webkitAudioContext)());
      if (ac.state === 'suspended') ac.resume().catch(e=>log('AudioContext resume failed',e));
    }catch(e){}
  }

  function crearPeer(ipDestino){
    if (peers[ipDestino] && peers[ipDestino].pc) return peers[ipDestino].pc;
    const pc = new RTCPeerConnection({ iceServers: (window.INTERCOM_CONFIG && window.INTERCOM_CONFIG.iceServers) ? window.INTERCOM_CONFIG.iceServers : [] });
    peers[ipDestino] = peers[ipDestino] || {};
    peers[ipDestino].pc = pc;

    iniciarStream().then(stream => {
      if (stream) stream.getTracks().forEach(track => pc.addTrack(track, stream));
    }).catch(e => log('start local stream failed', e));

    pc.onicecandidate = e => {
      if (e.candidate) sendWS({ type: 'candidate', to: ipDestino, sala: SALA, candidate: e.candidate, fromIp: MY_IP });
    };

    pc.ontrack = e => {
      log('ontrack from', ipDestino, e.streams);
      let audio = peers[ipDestino].audioEl;
      if (!audio) {
        audio = document.createElement('audio');
        audio.id = 'audio_remote_' + ipDestino;
        audio.autoplay = true;
        audio.controls = true;
        document.getElementById('audios')?.appendChild(audio) || document.body.appendChild(audio);
        peers[ipDestino].audioEl = audio;
      }
      audio.srcObject = e.streams[0];
      ensureAudioPlayback(audio);
      marcarBoton(ipDestino, 'red');
    };

    pc.onconnectionstatechange = () => {
      log('connectionState', ipDestino, pc.connectionState);
      if (pc.connectionState === 'disconnected' || pc.connectionState === 'failed' || pc.connectionState === 'closed') cleanupPeer(ipDestino);
    };

    return pc;
  }

  function cleanupPeer(ip){
    const p = peers[ip];
    if (!p) return;
    try{ if (p.pc) p.pc.close(); }catch(e){}
    if (p.audioEl) {
      try{ if (p.audioEl.srcObject) p.audioEl.srcObject.getTracks().forEach(t => t.stop && t.stop()); p.audioEl.srcObject = null; }catch(e){}
      p.audioEl.remove();
    }
    delete peers[ip];
    marcarBoton(ip, 'green');
    appendLog('Peer ' + ip + ' cleaned');
  }

  function marcarBoton(ip, color){
    const btn = document.querySelector('.icombtn[data-ip="'+ip+'"]');
    if (btn) { btn.classList.remove('green','gray','red'); btn.classList.add(color); }
  }

  function sendWS(obj){
    if (!ws || ws.readyState !== WebSocket.OPEN) { log('WS not open, drop', obj); return; }
    ws.send(JSON.stringify(obj));
  }

  async function onOffer(fromIp, sdp){
    log('Received offer from', fromIp);
    const pc = crearPeer(fromIp);
    try{
      await pc.setRemoteDescription(new RTCSessionDescription(sdp));
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      sendWS({ type: 'answer', to: fromIp, sala: SALA, sdp: pc.localDescription, fromIp: MY_IP });
      log('Sent answer to', fromIp);
    }catch(e){ log('handle offer error', e); }
  }

  async function onAnswer(fromIp, sdp){
    log('Received answer from', fromIp);
    const p = peers[fromIp];
    if (!p || !p.pc) { log('No pc for answer from', fromIp); return; }
    try{ await p.pc.setRemoteDescription(new RTCSessionDescription(sdp)); } catch(e){ log('setRemoteDescription(answer) failed', e); }
  }

  async function onCandidate(fromIp, candidate){
    log('Received candidate from', fromIp, candidate);
    const p = peers[fromIp];
    if (!p || !p.pc) crearPeer(fromIp);
    try{ await peers[fromIp].pc.addIceCandidate(new RTCIceCandidate(candidate)); } catch(e){ log('addIceCandidate failed', e); }
  }

  function setupWebSocket(){
    log('Connecting to signaling', SIGNALING_URL);
    ws = new WebSocket(SIGNALING_URL);

    ws.onopen = () => {
      log('WS open, sending join');
      ws.send(JSON.stringify({ type: 'join', ip: MY_IP, sala: SALA, user: MY_USER, token: TOKEN }));
    };

    ws.onerror = (e) => { log('WS error', e); appendLog('WS error'); };
    ws.onclose = (e) => { log('WS closed', e); appendLog('WS closed'); };

    ws.onmessage = async (evt) => {
      let msg; try { msg = JSON.parse(evt.data); } catch(e) { log('Invalid WS message', evt.data); return; }
      log('WS message', msg); appendLog('WS: ' + (msg.type || 'unknown'));
      switch(msg.type){
        case 'id': log('Assigned id', msg.id); break;
        case 'peers': log('Peers in sala', msg.peers); break;
        case 'join': log('Peer joined', msg.id, msg.ip); break;
        case 'leave': log('Peer left', msg.id, msg.ip); cleanupPeer(msg.ip); break;
        case 'offer': await onOffer(msg.fromIp||msg.from||msg.ip||msg.user, msg.sdp||msg.offer); break;
        case 'answer': await onAnswer(msg.fromIp||msg.from||msg.ip||msg.user, msg.sdp||msg.answer); break;
        case 'candidate': await onCandidate(msg.fromIp||msg.from||msg.ip||msg.user, msg.candidate||msg.ice); break;
        case 'error': log('Server error', msg.msg); appendLog('Server error: ' + (msg.msg||'')); break;
        default: log('Unknown ws type', msg.type);
      }
    };
  }

  function attachButtons(){
    document.querySelectorAll('.icombtn').forEach(btn=>{
      const ipDest = btn.getAttribute('data-ip');
      if (!ipDest || ipDest === 'ALL' || ipDest === MY_IP) return;
      let peer = null;
      btn.addEventListener('mousedown', async (e)=>{
        peer = crearPeer(ipDest);
        const offer = await peer.createOffer();
        await peer.setLocalDescription(offer);
        sendWS({ type: 'offer', to: ipDest, sala: SALA, sdp: peer.localDescription, fromIp: MY_IP });
        marcarBoton(ipDest, 'red');
      });
      const stopCall = (e) => { if (peer) try{ peer.close(); }catch(e){} peer = null; marcarBoton(ipDest,'green'); };
      btn.addEventListener('mouseup', stopCall);
      btn.addEventListener('mouseleave', stopCall);
    });
  }

  window.Intercom = {
    init: async function(){
      try{ await iniciarStream(); } catch(e){}
      setupWebSocket();
      attachButtons();
    },
    cleanupPeer, peers
  };

  document.addEventListener('DOMContentLoaded', function(){
    if (document.getElementById('btnStart')) {
      document.getElementById('btnStart').addEventListener('click', function(){
        Intercom.init(); this.disabled = true;
      });
    } else {
      Intercom.init();
    }
  });

})();