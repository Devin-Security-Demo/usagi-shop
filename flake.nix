{
  description = "うさぎ商事オンラインショップ 開発環境 (PHP 8.2 + Apache mod_php + MySQL 8.4 + MailHog)";

  inputs.nixpkgs.url = "github:NixOS/nixpkgs/nixos-26.05";

  outputs = { self, nixpkgs }:
    let
      systems = [ "x86_64-linux" "aarch64-linux" "x86_64-darwin" "aarch64-darwin" ];
      forAllSystems = f: nixpkgs.lib.genAttrs systems (system: f (import ./nix { pkgs = nixpkgs.legacyPackages.${system}; }));
    in
    {
      packages = forAllSystems (env: env.packages // { default = env.packages.dev; });

      apps = forAllSystems (env: {
        default = { type = "app"; program = "${env.packages.dev}/bin/dev"; };
        dev = { type = "app"; program = "${env.packages.dev}/bin/dev"; };
        dev-reset = { type = "app"; program = "${env.packages.dev-reset}/bin/dev-reset"; };
      });

      devShells = forAllSystems (env: { default = env.shell; });

      formatter = forAllSystems (env: env.pkgs.nixpkgs-fmt);
    };
}
