export function Footer() {
  return (
    <footer className="border-t border-border bg-background">
      <div className="mx-auto max-w-6xl px-4 py-10">
        <p className="font-poppins text-lg font-semibold text-foreground">Jeuncy</p>
        <p className="mt-1 font-inter text-sm text-muted-foreground">
          Ton alternance commence ici.
        </p>
        <p className="mt-6 font-inter text-xs text-muted-foreground">
          &copy; {new Date().getFullYear()} Jeuncy. Tous droits réservés.
        </p>
      </div>
    </footer>
  );
}
