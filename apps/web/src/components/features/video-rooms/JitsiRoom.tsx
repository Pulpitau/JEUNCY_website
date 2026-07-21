import { JitsiMeeting } from '@jitsi/react-sdk';

interface JitsiRoomProps {
  roomName: string;
}

// Instance publique meet.jit.si (voir CLAUDE.md section 7) : zero infra a
// gerer, mais pas de branding complet ni d'enregistrement des sessions —
// limite connue, migration vers un self-hosting Jitsi envisageable en V2.
export function JitsiRoom({ roomName }: JitsiRoomProps) {
  return (
    <div className="h-[70vh] w-full overflow-hidden rounded-md border border-border">
      <JitsiMeeting
        domain="meet.jit.si"
        roomName={roomName}
        configOverwrite={{
          startWithAudioMuted: true,
          prejoinPageEnabled: true,
          disableModeratorIndicator: true,
          // Partage d'ecran (desktop) prioritaire pour la demo logiciel, voir
          // CLAUDE.md section 7 — toolbar volontairement allegee sinon.
          toolbarButtons: [
            'microphone',
            'camera',
            'desktop',
            'fullscreen',
            'fodeviceselection',
            'hangup',
            'chat',
            'tileview',
            'settings',
          ],
        }}
        interfaceConfigOverwrite={{
          DISABLE_JOIN_LEAVE_NOTIFICATIONS: true,
          MOBILE_APP_PROMO: false,
        }}
        getIFrameRef={(iframeRef) => {
          iframeRef.style.height = '100%';
          iframeRef.style.width = '100%';
        }}
      />
    </div>
  );
}
