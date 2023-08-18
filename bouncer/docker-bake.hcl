group "default" {
  targets = [
    "bouncer",
  ]
}
variable "PLATFORMS" {
  default = [
    "arm64",
    "amd64",
  ]
}
target "bouncer" {
  context = "."
  dockerfile = "Dockerfile"
  platforms = PLATFORMS
  tags = [
    "benzine/bouncer:latest",
    "ghcr.io/benzine-framework/bouncer:latest",
  ]
  target = "bouncer"
}
