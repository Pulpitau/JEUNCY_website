import { apiRequest } from './client';

// Coordonnees servies par l'API et non codees ici : changer le numero de
// telephone se fait dans le .env du serveur, sans reconstruire le frontend.
export interface ContactDetails {
  email: string;
  phone: string | null;
}

export interface ContactMessageInput {
  name: string;
  email: string;
  organization?: string | null;
  subject: string;
  message: string;
  // Champ piege anti-robot : toujours envoye vide par un humain (voir
  // SendContactMessageRequest cote backend, qui le rejette s'il est rempli).
  website?: string;
}

export function getContactDetails() {
  return apiRequest<ContactDetails>('/contact');
}

export function sendContactMessage(input: ContactMessageInput) {
  return apiRequest<{ sent: boolean }>('/contact', { method: 'POST', body: input });
}
