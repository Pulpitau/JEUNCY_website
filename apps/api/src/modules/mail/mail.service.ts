import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Resend } from 'resend';

@Injectable()
export class MailService {
  private readonly logger = new Logger(MailService.name);
  private readonly resend: Resend | null;
  private readonly fromEmail: string;

  constructor(private readonly configService: ConfigService) {
    const apiKey = this.configService.get<string>('RESEND_API_KEY');
    // Sans cle configuree (dev sans compte Resend), on log au lieu d'envoyer.
    this.resend = apiKey ? new Resend(apiKey) : null;
    this.fromEmail =
      this.configService.get<string>('RESEND_FROM_EMAIL') ?? 'no-reply@jeuncy.fr';
  }

  async sendPasswordResetEmail(to: string, resetUrl: string) {
    if (!this.resend) {
      this.logger.warn(
        `RESEND_API_KEY absent : email de reinitialisation non envoye a ${to} (lien: ${resetUrl})`,
      );
      return;
    }

    await this.resend.emails.send({
      from: this.fromEmail,
      to,
      subject: 'Réinitialise ton mot de passe Jeuncy',
      html: `
        <p>Tu as demandé la réinitialisation de ton mot de passe Jeuncy.</p>
        <p><a href="${resetUrl}">Clique ici pour choisir un nouveau mot de passe</a></p>
        <p>Ce lien expire dans 1 heure. Si tu n'es pas à l'origine de cette demande, ignore cet email.</p>
      `,
    });
  }
}
