# YEntityGeneratorBundle

YEntityGeneratorBundle automatically makes entities from configurations 
for your Symfony applications with unprecedented simplicity.

## What does this bundle?

### Step 1: Configure your entities

`./config/uay_entities.yaml`
```yaml
uay_entities:
  entities:
    Author:
      name:
        type: string
        length: 100
      pages:
        relation: many
        target: Page
      role:
        type: enum
        target: Role
    Page:
      title:
        type: string
        length: 100
      description:
        type: string
        length: 255
      sample:
        type: string
        length: 255
        nullable: true
      author:
        relation: one
        target: Author
    Role:
      Unknown: 1
      Guest: 2
      Member: 3
      Assistant: 4
      Admin: 5
      Owner: 6
```

### Step 2: Call the console command

```
php bin/console entities:generate
```

### Step 3: Update the database schema

**Attention**: Be careful with this step!

```
php bin/console doctrine:schema:update --force
```

### Step 4: Done

Generated UML-diagram `./readme/entities.png`:

![UML-diagram of the generated entities](./readme/entities.png?raw=true)

You will now find the configurated entities and files:

```
.
├── ...
├── entities
│   ├── entities.png
│   └── entities.txt
├── src
│   ├── ...
│   ├── Entity
│   │   ├── ...
│   │   ├── Generated
│   │   │   ├── AuthorGenerated.php
│   │   │   └── PageGenerated.php
│   │   ├── Author.php
│   │   ├── Page.php
│   │   └── ...
│   ├── Enum
│   │   ├── ...
│   │   ├── Role.php
│   │   └── ...
│   ├── Repository
│   │   ├── ...
│   │   ├── AuthorRepository.php
│   │   ├── PageRepository.php
│   │   └── ...
│   └── ...
└── ...
```

## Usage

Call `php bin/console entities:generate` to generate the entities from `uay_entities.yaml`.

## Installation

Add the repository (to the `composer.json` file): 
```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/uay/YEntityGeneratorBundle"
    }
  ]
}
```

And add the package to `require-dev` and replace `<version>` with the current version: 
```json
{
  "require-dev": {
    "uay/y-entity-generator-bundle": "<version>",
  }
}
```

## Configuration

```yaml
uay_entities:
  imports:                      # Define your entity imports
    Assert: 'Symfony\Component\Validator\Constraints'
    # ...
  entities:                     # Define your entities here like in the example above
    # ...
  uml:                          # Define your uml settings here
    valid: false                # By default the generated uml is not valid, change this here
  namespace:                    # Define your namespace settings here
    app: App
    base: Generated
    enum: Enum
    entity: Entity
    repository: Repository
  classPostfix:                 # Define your class postfixes here
    entity: Generated
    repository: Repository
  fixtures:                     # Define your doctrine fixtures data here
    # ...
    # ATTENTION: This is not implemented yet!
```

## Features

- [x] Generate base entities
- [x] Generate app entities
- [x] Generate app repositories
- [x] Generate app enums
- [x] Configure generated names
- [x] Better UML configuration
- [ ] Recursive namespace configurations
- [ ] Config validation
- [ ] Generate base repositories
- [ ] Generate base enums
- [ ] Allow to generate app entities only
- [ ] ... we're open for inspiration

If you want to see some of the features, you should not wait for them. 
There is no guarantee that we will implement the missing features. 
Instead you would better start contributing to this repository and implement them by yourself.

## Versioning

`x.y.z`

- `x`: **Major**-Version: Breaking changes
- `y`: **Minor**-Version: Additional features (non-breaking)
- `z`: **Patch**-Version: Bugfixes and code improvements (non-breaking)

## License

This software is published under the [MIT License](LICENSE.md)
