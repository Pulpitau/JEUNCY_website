import { Routes, Route } from 'react-router-dom';
import { UserRole } from '@jeuncy/shared';
import { Navbar } from '@/components/Navbar';
import { Footer } from '@/components/Footer';
import { RequireAuth } from '@/components/RequireAuth';
import { Home } from '@/pages/Home';
import { About } from '@/pages/About';
import { Login } from '@/pages/Login';
import { Register } from '@/pages/Register';
import { ForgotPassword } from '@/pages/ForgotPassword';
import { ResetPassword } from '@/pages/ResetPassword';
import { AuthCallback } from '@/pages/AuthCallback';
import { Profile } from '@/pages/Profile';
import { OrganizationProfile } from '@/pages/OrganizationProfile';
import { MyJobOffers } from '@/pages/MyJobOffers';
import { JobOffers } from '@/pages/JobOffers';
import { JobOfferDetail } from '@/pages/JobOfferDetail';
import { MyApplications } from '@/pages/MyApplications';
import { MyVideoRooms } from '@/pages/MyVideoRooms';
import { DemoRoom } from '@/pages/DemoRoom';
import { Admin } from '@/pages/Admin';
import { MyPayments } from '@/pages/MyPayments';
import { LegalNotice } from '@/pages/LegalNotice';
import { PrivacyPolicy } from '@/pages/PrivacyPolicy';

export default function App() {
  return (
    <div className="flex min-h-screen flex-col">
      <Navbar />
      <div className="flex-1">
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/a-propos" element={<About />} />
          <Route path="/offres" element={<JobOffers />} />
          <Route path="/offres/:id" element={<JobOfferDetail />} />
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
          <Route
            path="/mes-paiements"
            element={
              <RequireAuth role={[UserRole.COMPANY, UserRole.CFA]}>
                <MyPayments />
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
