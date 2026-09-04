import { Routes, Route } from 'react-router-dom';
import { UserRole } from '@jeuncy/shared';
import { Navbar } from '@/components/Navbar';
import { Footer } from '@/components/Footer';
import { CompleteProfileBanner } from '@/components/CompleteProfileBanner';
import { RequireAuth } from '@/components/RequireAuth';
import { Home } from '@/pages/Home';
import { About } from '@/pages/About';
import { Pricing } from '@/pages/Pricing';
import { Contact } from '@/pages/Contact';
import { Login } from '@/pages/Login';
import { Register } from '@/pages/Register';
import { ForgotPassword } from '@/pages/ForgotPassword';
import { ResetPassword } from '@/pages/ResetPassword';
import { AuthCallback } from '@/pages/AuthCallback';
import { Profile } from '@/pages/Profile';
import { OrganizationProfile } from '@/pages/OrganizationProfile';
import { MyJobOffers } from '@/pages/MyJobOffers';
import { Cvtheque } from '@/pages/Cvtheque';
import { CvthequeCandidate } from '@/pages/CvthequeCandidate';
import { JobOffers } from '@/pages/JobOffers';
import { JobOfferDetail } from '@/pages/JobOfferDetail';
import { MyApplications } from '@/pages/MyApplications';
import { MyVideoRooms } from '@/pages/MyVideoRooms';
import { DemoRoom } from '@/pages/DemoRoom';
import { Admin } from '@/pages/Admin';
import { AdminJobOfferPreview } from '@/pages/AdminJobOfferPreview';
import { MyPayments } from '@/pages/MyPayments';
import { LegalNotice } from '@/pages/LegalNotice';
import { PrivacyPolicy } from '@/pages/PrivacyPolicy';
import { Companies } from '@/pages/Companies';
import { CompanyProfile } from '@/pages/CompanyProfile';
import { CfaOrganizations } from '@/pages/CfaOrganizations';
import { CfaOrganizationProfile } from '@/pages/CfaOrganizationProfile';
import { AccountPrivacy } from '@/pages/AccountPrivacy';

export default function App() {
  return (
    <div className="flex min-h-screen flex-col">
      <Navbar />
      {/* Sous la Navbar et hors du sticky : le bandeau defile avec la page
          plutot que de manger 60px de hauteur utile en permanence sur
          mobile. Il ne s'affiche que pour un candidat sans profil. */}
      <CompleteProfileBanner />
      <div className="flex-1">
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/a-propos" element={<About />} />
          <Route path="/contact" element={<Contact />} />
          {/* Route publique, mais l'onglet correspondant n'apparait dans la
              barre que pour une entreprise ou un CFA connecte (voir
              navLinksFor dans Navbar.tsx) : le lien est communique aux
              prospects apres un premier echange. */}
          <Route path="/tarifs" element={<Pricing />} />
          <Route path="/offres" element={<JobOffers />} />
          <Route path="/offres/:id" element={<JobOfferDetail />} />
          <Route path="/entreprises" element={<Companies />} />
          <Route path="/entreprises/:id" element={<CompanyProfile />} />
          <Route path="/cfa" element={<CfaOrganizations />} />
          <Route path="/cfa/:id" element={<CfaOrganizationProfile />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/forgot-password" element={<ForgotPassword />} />
          <Route path="/reset-password" element={<ResetPassword />} />
          <Route path="/auth/callback" element={<AuthCallback />} />
          <Route
            path="/profile"
            element={
              <RequireAuth role={UserRole.CANDIDATE}>
                <Profile />
              </RequireAuth>
            }
          />
          <Route
            path="/organization"
            element={
              <RequireAuth role={[UserRole.COMPANY, UserRole.CFA]}>
                <OrganizationProfile />
              </RequireAuth>
            }
          />
          <Route
            path="/mes-offres"
            element={
              <RequireAuth role={[UserRole.COMPANY, UserRole.CFA]}>
                <MyJobOffers />
              </RequireAuth>
            }
          />
          {/* CVtheque : RequireAuth ne filtre que le ROLE. La garde
              d'abonnement, elle, est cote serveur (402) et la page affiche
              alors son ecran d'accroche — voir CvthequeService.
              ADMIN inclus : l'equipe Jeuncy consulte la CVtheque comme un
              client abonne, sans souscrire d'abonnement (voir
              SubscriptionService::hasPaidAccess). */}
          <Route
            path="/candidats"
            element={
              <RequireAuth
                role={[UserRole.COMPANY, UserRole.CFA, UserRole.ADMIN, UserRole.STAFF]}
              >
                <Cvtheque />
              </RequireAuth>
            }
          />
          <Route
            path="/candidats/:id"
            element={
              <RequireAuth
                role={[UserRole.COMPANY, UserRole.CFA, UserRole.ADMIN, UserRole.STAFF]}
              >
                <CvthequeCandidate />
              </RequireAuth>
            }
          />
          <Route
            path="/mes-candidatures"
            element={
              <RequireAuth role={UserRole.CANDIDATE}>
                <MyApplications />
              </RequireAuth>
            }
          />
          <Route path="/demo/:roomId" element={<DemoRoom />} />
          <Route
            path="/mes-visios"
            element={
              <RequireAuth role={[UserRole.COMPANY, UserRole.CFA]}>
                <MyVideoRooms />
              </RequireAuth>
            }
          />
          <Route
            path="/admin"
            element={
              <RequireAuth role={UserRole.ADMIN}>
                <Admin />
              </RequireAuth>
            }
          />
          {/* Apercu du rendu public d'une offre, brouillon compris — meme
              composant de rendu que /offres/:id, garde ADMIN cote client ET
              cote serveur (routes/api/admin.php). */}
          <Route
            path="/admin/offres/:id/apercu"
            element={
              <RequireAuth role={UserRole.ADMIN}>
                <AdminJobOfferPreview />
              </RequireAuth>
            }
          />
          <Route
            path="/mes-paiements"
            element={
              <RequireAuth role={[UserRole.COMPANY, UserRole.CFA]}>
                <MyPayments />
              </RequireAuth>
            }
          />
          <Route
            path="/mon-compte/confidentialite"
            element={
              <RequireAuth>
                <AccountPrivacy />
              </RequireAuth>
            }
          />
          <Route path="/mentions-legales" element={<LegalNotice />} />
          <Route path="/confidentialite" element={<PrivacyPolicy />} />
        </Routes>
      </div>
      <Footer />
    </div>
  );
}
